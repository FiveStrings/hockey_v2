<?php
require_once('../include/config.inc.php');

$seasons = array();
$db->query('SELECT * FROM season ORDER BY seasonStart DESC');
$mostRecentSeason = false;
while ($row = $db->fetch_assoc()) {
	if ($mostRecentSeason === false) $mostRecentSeason = $row['seasonID'];
	$seasons[$row['seasonID']] = $row;
}

$seasonID = isset($_GET['seasonID']) ? $_GET['seasonID'] : $mostRecentSeason;

$gameListSQL = <<<SQLEND
select 
	game.*,
	homeTeam.teamName homeTeamName,
	awayTeam.teamName awayTeamName,
	sum(if(player.teamID = homeTeamID, 1, 0)) homeGoals,
	sum(if(player.teamID = awayTeamID, 1, 0)) awayGoals,
	if(max(event.period) > 3, "OT", "") isOT
from game 
	LEFT JOIN event using (gameID)
	LEFT JOIN player using (playerID)
	LEFT JOIN team as homeTeam on (game.homeTeamID = homeTeam.teamID)
	LEFT JOIN team as awayTeam on (game.awayTeamID = awayTeam.teamID)
WHERE game.seasonID = ? AND event.eventType in ('goal','ppgoal','engoal','shgoal') GROUP BY gameID
ORDER BY gameTime DESC;
SQLEND;
$db->query($gameListSQL, array($seasonID));

$gameRows = '';
while ($row = $db->fetch_assoc()) {
	$theDate = date('D Y-m-d', $row['gameTime']);
	$theTime = date('h:i:s a', $row['gameTime']);
	$theAT = $row['homeTeamID'] == 1 ? "&nbsp;" : "@";
	$theOpponent = $row['homeTeamID'] == 1 ? $row['awayTeamName'] : $row['homeTeamName'];
	$theWin = ($row['homeTeamID'] == 1) ? ($row['homeGoals'] > $row['awayGoals'] ? "W" : "L") : ($row['homeGoals'] < $row['awayGoals'] ? "W" : "L"); 
	$theOT = $row['isOT'];
	$rbGoals = $row['homeTeamID'] == 1 ? $row['homeGoals'] : $row['awayGoals'];
	$oppGoals = $row['homeTeamID'] == 1 ? $row['awayGoals'] : $row['homeGoals'];

	$gameRows .= <<<ROWEND
		<tr>
			<td><a href="game.php?gameID=$row[gameID]">$theDate</a></td>
			<td>$theTime</td>
			<td>$theAT</td>
			<td>$theOpponent</td>
			<td>$theWin</td>
			<td>$theOT</td>
			<td>$rbGoals</td>
			<td>$oppGoals</td>
		</tr>
ROWEND;
}

$sort = isset($_GET['sort']) ? $_GET['sort'].' DESC' : "CAST(player.number as unsigned) ASC";
$playerInfo = array();
/*$sql = <<<SQLEND
select
	player.number,
	player.firstName,
	player.nickName,
	player.lastName,
	sum(if(eventType in ('goal','ppgoal','engoal','shgoal'), 1, 0)) goals,
	sum(if(eventType in ('assist1','assist2'), 1, 0)) assists,
	sum(if(eventType in ('goal','ppgoal','engoal','shgoal','assist1','assist2'), 1, 0)) points,
	if(sum(penaltyMinutes) is null, 0, sum(penaltyMinutes)) pim
from event
join player using (playerID)
where teamID = 1
GROUP BY playerID
ORDER BY $sort
SQLEND;*/
$sql = "SELECT player.*, sum(G) G, sum(A) A, sum(PTS) PTS, sum(PIM) PIM FROM player_game_stat JOIN player USING (playerID) GROUP BY player_game_stat.playerID ORDER BY $sort";
$db->query($sql);
$playerRows = '';
//$goalieRows = '';
while ($row = $db->fetch_assoc()) {
	if ($row['number'] === '?') continue;
	if ($row['PTS'] || $row['PIM']) {
		$lName = (isset($row['lastName']) && strlen($row['lastName'])) ? ' '.$row['lastName'][0].'.' : '';
		$thePlayer = "$row[firstName]$lName";
		$playerRows .= <<<ROWEND
			<tr>
				<td><a href="player.php?playerID=$row[playerID]">$row[number]</a></td>
				<td>$thePlayer</td>
				<td class="small">$row[G]</td>
				<td class="small">$row[A]</td>
				<td class="small">$row[PTS]</td>
				<td class="small">$row[PIM]</td>
			</tr>
ROWEND;
	}
}

$sql = 'SELECT player.*, SUM(GA) GA, SUM(SA) SA, SUM(W) W, SUM(L) L FROM goalie_game_stat JOIN player USING (playerID) GROUP BY goalie_game_stat.playerID';
$db->query($sql);
$goalieRows = '';
while ($row = $db->fetch_assoc()) {
	if ($row['number'] === '?') continue;
	if ($row['GA']) {
		$lName = (isset($row['lastName']) && strlen($row['lastName'])) ? ' '.$row['lastName'][0].'.' : '';
		$thePlayer = "$row[firstName]$lName";
		$SV = ($row['GA']) ? ($row['SA'] - $row['GA']) : 0;
		$SVP = ($row['SA'] > 0) ? number_format($SV / $row['SA'], 3) : 0;
		$goalieRows .= <<<ROWEND
			<tr>
				<td><a href="player.php?playerID=$row[playerID]">$row[number]</a></td>
				<td>$thePlayer</td>
				<td class="small">$row[W]-$row[L]</td>
				<td class="small">$row[GA]</td>
				<td class="small">$row[SA]</td>
				<td class="small">$SV</td>
				<td class="small">$SVP</td>
			</tr>
ROWEND;
	}
}

require_once('../include/header.inc.php');

print <<<PAGEEND
<h2>Games</h2>
<table cellspacing="0" cellpadding="0">
	<tr class="bold">
		<td>Date</td>
		<td>Time</td>
		<td>&nbsp</td>
		<td>Opponent</td>
		<td>&nbsp;</td>
		<td>&nbsp;</td>
		<td title="Red Beards' Score">Tm</td>
		<td title="Opponent Score">Opp</td>
	</tr>
	$gameRows
</table>
<hr>
<h2>Players</h2>
<table cellspacing="0" cellpadding="0">
	<tr class="bold">
		<td><a href="index.php">#</a></td>
		<td>Name</td>
		<td class="small"><a href="?sort=G">G</a></td>
		<td class="small"><a href="?sort=A">A</a></td>
		<td class="small"><a href="?sort=PTS">PTS</a></td>
		<td class="small"><a href="?sort=PIM">PIM</a></td>
	</tr>
	$playerRows
</table>
<h2>Goalies</h2>
<table cellspacing="0" cellpadding="0">
	<tr class="bold">
		<td><a href="index.php">#</a></td>
		<td>Name</td>
		<td class="small">W-L</td>
		<td class="small">GA</td>
		<td class="small">SA</td>
		<td class="small">SV</td>
		<td class="small">SV%</td>
	</tr>
	$goalieRows
</table>

<br><br><form action="feedback.php" method="post">Suggest a correction: <br><textarea name="correctionText" rows="5" cols="44" placeholder="type in the correction here"></textarea><br><input type="submit"></form>
PAGEEND;
require_once('../include/footer.inc.php');
?>

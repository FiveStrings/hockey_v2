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

if (!isset($_GET['playerID'])) die("Invalid Player");

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
WHERE game.seasonID = ? AND event.eventType in ('goal','ppgoal','engoal','shgoal') GROUP BY gameID;
SQLEND;
$db->query($gameListSQL, array($seasonID));
while ($row = $db->fetch_assoc()) $games[$row['gameID']] = $row;

//print_r($games);
/*$sql = <<<SQLEND
	SELECT
		gameID,
		player,
		sum(if(stat="goal",count,0)) goals,
		sum(if(stat="assist",count,0)) assists,
		sum(if(stat="goal" OR stat="assist", count, 0)) points,
		sum(if(stat="minutes", count, 0)) minutes,
		sum(if(stat="ga", count, 0)) ga,
		sum(if(stat="sa", count, 0)) sa
	FROM
		(SELECT scorer player, count(*) count, "goal" stat, gameID FROM game_goal where teamID = 1 AND scorer = '$_GET[playerID]' group by gameID UNION
		SELECT assist1 player, count(*) count, "assist" stat, gameID FROM game_goal where teamID = 1 AND assist1 = '$_GET[playerID]' group by gameID UNION
		SELECT assist2 player, count(*) count, "assist" stat, gameID FROM game_goal where teamID = 1 AND assist2 = '$_GET[playerID]' group by gameID UNION
		SELECT player, sum(minutes) count, "minutes" stat, gameID FROM game_penalty WHERE teamID = 1 AND player = '$_GET[playerID]' GROUP BY gameID UNION
		select awayGoalie, sum(if(game_goal.teamID=game.homeTeamID,1,0)) count, "ga" stat, game.gameID from game join game_goal on (game.gameID = game_goal.gameID) WHERE awayTeamID = 1 AND awayGoalie = '$_GET[playerID]' group by gameID UNION
		select awayGoalie, sum(homeShots1 + homeShots2 + homeShots3) count, "sa" stat, game.gameID from game WHERE awayTeamID = 1 AND awayGoalie = '$_GET[playerID]' group by gameID UNION
		select homeGoalie, sum(if(game_goal.teamID=game.awayTeamID,1,0)) count, "ga" stat, game.gameID from game join game_goal on (game.gameID = game_goal.gameID) WHERE homeTeamID = 1 AND homeGoalie = '$_GET[playerID]' group by gameID UNION
		select homeGoalie, sum(awayShots1 + awayShots2 + awayShots3) count, "sa" stat, game.gameID from game WHERE homeTeamID = 1 AND homeGoalie = '$_GET[playerID]' group by gameID
	) stats join game using (gameID)
	GROUP BY gameID
	ORDER BY gameTime DESC
SQLEND;*/
$playerStats = array();
$sql = 'SELECT * FROM player JOIN player_game_stat USING (playerID) WHERE player.playerID = ?';
$db->query($sql, array($_GET['playerID']));
while ($row = $db->fetch_assoc()) $playerStats[$row['gameID']] = $row;

$sql = 'SELECT * FROM player JOIN goalie_game_stat USING (playerID) WHERE player.playerID = ?';
$db->query($sql, array($_GET['playerID']));
while ($row = $db->fetch_assoc()) {
	if (isset($playerStats[$row['gameID']])) $playerStats[$row['gameID']] = array_merge($playerStats[$row['gameID']], $row);
	else $playerStats[$row['gameID']] = $row;
}

$thePlayer = '';
$theNumber = '';
$gameRows = $goalieRows = '';
$gameHeader = $goalieHeader = 'hidden';
$totals = array(
	'G' => 0,
	'A' => 0,
	'PTS' => 0,
	'PIM' => 0,
	'GA' => 0,
	'SA' => 0,
	'SV' => 0,
	'SVP' => 0,
);
	
foreach ($playerStats as $row) {
	$lName = isset($row['lastName']) ? ' '.$row['lastName'][0].'.' : '';
	$thePlayer = "$row[firstName]$lName";
	$theNumber = "$row[number]";
	//print "<pre>";
	//print_r($row);
	//print_r($games);
	$row = array_merge($row, $games[$row['gameID']]);
	//print_r($row);
	$theDate = date('D Y-m-d', $row['gameTime']);
	$theTime = date('h:i:s a', $row['gameTime']);
	$theAT = $row['homeTeamID'] == 1 ? "&nbsp;" : "@";
	$theOpponent = $row['homeTeamID'] == 1 ? $row['awayTeamName'] : $row['homeTeamName'];
	$theWin = ($row['homeTeamID'] == 1) ? ($row['homeGoals'] > $row['awayGoals'] ? "W" : "L") : ($row['homeGoals'] < $row['awayGoals'] ? "W" : "L"); 
	$rbScore = $row['homeTeamID'] == 1 ? $row['homeGoals'] : $row['awayGoals'];
	$oppScore = $row['homeTeamID'] == 1 ? $row['awayGoals'] : $row['homeGoals'];

	if (isset($row['PTS']) || isset($row['PIM'])) {
		$totals['G'] += $row['G'];
		$totals['A'] += $row['A'];
		$totals['PTS'] += $row['PTS'];
		$totals['PIM'] += $row['PIM'];
		$gameHeader = '';
		$gameRows .= <<<ROWEND
			<tr>
				<td><a href="game.php?gameID=$row[gameID]">$theDate</a></td>
				<td>$theTime</td>
				<td>$theAT</td>
				<td>$theOpponent</td>
				<td>$theWin</td>
				<td>$rbScore</td>
				<td>$oppScore</td>
				<td class="small">$row[G]</td>
				<td class="small">$row[A]</td>
				<td class="small">$row[PTS]</td>
				<td class="small">$row[PIM]</td>
			</tr>
ROWEND;
	}
	if (isset($row['GA'])) {
		$goalieHeader = '';
		$sv = ($row['GA']) ? ($row['SA'] - $row['GA']) : 0;
		$svp = ($row['SA']) ? number_format($sv / $row['SA'], 3) : 0;
		$totals['GA'] += $row['GA'];
		$totals['SA'] += $row['SA'];
		$totals['SV'] += $sv;
		$totals['SVP'] = ($totals['SA']) ? number_format($totals['SV'] / $totals['SA'], 3) : 0;
		$goalieRows .= <<<ROWEND
			<tr>
				<td><a href="game.php?gameID=$row[gameID]">$theDate</a></td>
				<td>$theTime</td>
				<td>$theAT</td>
				<td>$theOpponent</td>
				<td>$theWin</td>
				<td>$rbScore</td>
				<td>$oppScore</td>
				<td class="small">$row[GA]</td>
				<td class="small">$row[SA]</td>
				<td class="small">$sv</td>
				<td class="small">$svp</td>
			</tr>
ROWEND;
	}
}


print <<<PAGEEND
<html>
<head>
<style type="text/css">
	table {
		border-collapse: collapse;
	}

	table td {
		vertical-align: top;
		margin: 0;
		padding: 3px;
		border: 1px solid black;
		text-align: center;
	}
	td.small {
		width: 37px;
		text-align: center;
	}

	.center { text-align: center; }
	.bold { font-weight: bold; }
	.border { border: 1px solid black; }
	.tdborders > tbody > tr > td { border: 1px solid black; }
	.hidden { display: none;} 
</style>
</head>
<body>
<h1><img src="logo.png" style="height: 50px; margin-right: 15px;">Red Beards Stats System - Summer 2014</h1>
<a href="index.php">Back to Home</a>
<h2>$thePlayer (#$theNumber)</h1>
<div class="$gameHeader">
<h2>Player Stats</h2>
<table cellspacing="0" cellpadding="0">
	<tr class="bold">
		<td>Date</td>
		<td>Time</td>
		<td>&nbsp</td>
		<td>Opponent</td>
		<td>&nbsp;</td>
		<td title="Red Beards' Score">Tm</td>
		<td title="Opponent Score">Opp</td>
		<td class="small"><a href="?sort=goals">G</a></td>
		<td class="small"><a href="?sort=assists">A</a></td>
		<td class="small"><a href="?sort=points">PTS</a></td>
		<td class="small"><a href="?sort=minutes">PIM</a></td>
	</tr>
	$gameRows
	<tr>
		<td colspan="7">Totals:</td>
		<td class="small">$totals[G]</td>
		<td class="small">$totals[A]</td>
		<td class="small">$totals[PTS]</td>
		<td class="small">$totals[PIM]</td>
	</tr>
</table></div>

<div class="$goalieHeader">
<h2>Goalie Stats</h2>
<table cellspacing="0" cellpadding="0">
	<tr class="bold">
		<td>Date</td>
		<td>Time</td>
		<td>&nbsp</td>
		<td>Opponent</td>
		<td>&nbsp;</td>
		<td title="Red Beards' Score">Tm</td>
		<td title="Opponent Score">Opp</td>
		<td class="small"><a href="?sort=ga">GA</a></td>
		<td class="small"><a href="?sort=sa">SA</a></td>
		<td class="small"><a href="?sort=sv">SV</a></td>
		<td class="small"><a href="?sort=svp">SV%</a></td>
	</tr>
	$goalieRows
	<tr>
		<td colspan="7">Totals:</td>
		<td class="small">$totals[GA]</td>
		<td class="small">$totals[SA]</td>
		<td class="small">$totals[SV]</td>
		<td class="small">$totals[SVP]</td>
	</tr>
</table>
</div>
<br><br><form action="feedback.php" method="post">Suggest a correction: <br><textarea name="correctionText" rows="5" cols="44" placeholder="type in the correction here"></textarea><br><input type="submit"></form>
</body>
</html>
PAGEEND;
?>

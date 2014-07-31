<?php
require_once('../include/config.inc.php');
if (isset($_POST) && count($_POST)) {
	//print "<pre>";
	//print_r($_POST);
	//print "</pre>";

	$return = '';
	switch ($_POST['mode']) {
		case 'updatestats':
			$db->query('replace into goalie_game_stat (playerID, gameID, GA, SA, W, L) SELECT playerID, gameID, sum(GA) GA, sum(SA) SA, sum(W) W, sum(L) L FROM ( select player.playerID, game.gameID, count(distinct event.eventID) "GA", 0 "SA", 0 "W", 0 "L" FROM game join event using (gameID) join player as eventplayer on (eventplayer.playerID = event.playerID) join player on (player.playerID = homeGoalieID OR player.playerID = awayGoalieID) WHERE player.teamID = 1 AND eventplayer.teamID != 1 AND event.eventType in ("goal","ppgoal","shgoal") group by player.playerID, game.gameID UNION select player.playerID, game.gameID, 0 "GA", sum(shots) "SA", 0 "W", 0 "L" from game join player on (player.playerID = homeGoalieID OR player.playerID = awayGoalieID) join shot on (game.gameID = shot.gameID) WHERE shot.teamID != 1 AND player.teamID = 1 group by player.playerID, game.gameID UNION select player.playerID, game.gameID, 0 "GA", 0 "SA", sum(if(winners.teamID = player.teamID, 1, 0)) "W", sum(if(winners.teamID != player.teamID, 1, 0)) "L" FROM game JOIN (SELECT gameID, LEFT(group_concat(teamID), greatest(LOCATE(",", group_concat(teamID))-1,length(group_concat(teamID)))) teamID FROM (select gameID, teamID, sum(if(eventType in("goal","ppgoal","engoal","shgoal"), 1, 0)) goals FROM event join player using (playerID) GROUP BY event.gameID, teamID) x GROUP BY gameID ORDER BY gameID, goals DESC) winners using (gameID) JOIN player on (player.playerID = homeGoalieID OR player.playerID = awayGoalieID) WHERE player.teamID = 1 GROUP BY player.playerID, game.gameID) x GROUP BY playerID, gameID');
			$db->query('replace into player_game_stat (playerID, gameID, G, A, PTS, PIM) SELECT player.playerID, gameID, sum(if(eventType in ("goal","ppgoal","engoal","shgoal"), 1, 0)) goals, sum(if(eventType in ("assist1","assist2"), 1, 0)) assists, sum(if(eventType in ("goal","ppgoal","engoal","shgoal","assist1","assist2"), 1, 0)) points, if(sum(penaltyMinutes) is null, 0, sum(penaltyMinutes)) pim from event join player using (playerID) where teamID = 1 GROUP BY playerID, gameID');
			break;
		case 'season':
			$rawDate = explode('-', $_POST['date']);
			$theTime = mktime(0, 0, 0, $rawDate[1], $rawDate[2], $rawDate[0]);
			$db->insert('season',
				array(
					'seasonName' => $_POST['seasonName'],
					'seasonStart' => $theTime,
				)
			);
			$seasonSelect = '';
			$db->query("SELECT * FROM season order by seasonStart DESC");
			while ($row = $db->fetch_assoc()) {
				$seasonSelect .= "<option value='$row[seasonID]'>$row[seasonName]</option>";
			}
			$return = $seasonSelect;
			break;
		case 'team':
			$db->insert('team',
				array(
					'teamName' => $_POST['teamName'],
				)
			);
			$teamSelect = '<option value="">Select Team</option>';
			$db->query("SELECT * FROM team");
			while ($row = $db->fetch_assoc()) {
				$teamSelect .= "<option value='$row[teamID]'>$row[teamName]</option>";
			}
			$return = $teamSelect;
			break;
		case 'player':
			$db->insert('player',
				array(
					'teamID' => $_POST['teamID'],
					'number' => $_POST['number'],
					'firstName' => $_POST['firstName'],
					'lastName' => $_POST['lastName'],
					'nickName' => $_POST['nickName'],
				)
			);
			$playerSelect = '<option value="">Select Player</option>';
			$db->query("SELECT * FROM player LEFT JOIN team USING (teamID) ORDER BY teamID, CAST(number as unsigned), firstname, lastname");
			while ($row = $db->fetch_assoc()) {
				$nick = strlen($row['nickName']) ? "\"$row[nickName]\" " : '';
				$thePlayer = "$row[number] $row[firstName] $nick$row[lastName] ($row[teamName])";
				$playerSelect .= "<option teamID='$row[teamID]' value='$row[playerID]'>$thePlayer</option>";
			}
			$playerList = '';
			$db->query("SELECT * FROM player LEFT JOIN team USING (teamID) ORDER BY teamID, CAST(number as unsigned), firstname, lastname");
			while ($row = $db->fetch_assoc()) {
				$nick = strlen($row['nickName']) ? "\"$row[nickName]\" " : '';
				$thePlayer = "$row[number] $row[firstName] $nick$row[lastName] ($row[teamName])";
				$playerList .= "$thePlayer<br>";
			}
			$return = array('selects'=>$playerSelect, 'list'=>$playerList);
			break;
		case 'game':
			$rawDate = explode('-', $_POST['date']);
			$rawTime = explode('-', $_POST['time']);
			$theTime = mktime($rawTime[0], $rawTime[1], 0, $rawDate[1], $rawDate[2], $rawDate[0]);


			//$q = $db->query("INSERT INTO game (gameTime, homeTeamID, awayTeamID, homeShots1, homeShots2, homeShots3, awayShots1, awayShots2, awayShots3, notes, homeGoalie, awayGoalie) VALUES ($theTime, $_POST[homeTeamID], $_POST[awayTeamID], $_POST[homeShots1], $_POST[homeShots2], $_POST[homeShots3], $_POST[awayShots1], $_POST[awayShots2], $_POST[awayShots3], '$_POST[notes]', '$_POST[homeGoalie]', '$_POST[awayGoalie]')");
			$db->insert('game',
				array(
					'seasonID' => $_POST['seasonID'],
					'gameTime' => $theTime,
					'homeTeamID' => $_POST['homeTeamID'],
					'awayTeamID' => $_POST['awayTeamID'],
					//'shots' => $shotsString,
					'homeGoalieID' => $_POST['homeGoalie'],
					'awayGoalieID' => $_POST['awayGoalie'],
					'notes' => $_POST['notes'],
				)
			);
			$gameID = $db->insert_id();

			$shots = array();
			foreach ($_POST['homeShots'] as $pp=>$ss) $shots[$pp][] = array('team'=>$_POST['homeTeamID'], 'shots'=>$ss);
			foreach ($_POST['awayShots'] as $pp=>$ss) $shots[$pp][] = array('team'=>$_POST['awayTeamID'], 'shots'=>$ss);
			$shotsString = '';
			$delim = '';
			foreach ($shots as $pp=>$ss) {
				foreach ($ss as $info) {
					if ($pp > 3 && $info['shots'] < 1) continue;
					$db->insert('shot',
						array(
							'gameID' => $gameID,
							'teamID' => $info['team'],
							'period' => $pp,
							'shots' => $info['shots'],
						)
					);
				}
			}

			foreach ($_POST['goals'] as $goal) {
				if (strlen($goal['period']) < 1) continue;
				$gMin = 15 - $goal['clockMin'];
				$gSec = 60 - $goal['clockSec'];
				$goalTime = ($gMin * 60) + ($gSec) - 60;
				$teamID = (isset($goal['home'])) ? $_POST['homeTeamID'] : $_POST['awayTeamID'];
				$db->insert('event',
					array(
						'gameID' => $gameID,
						'playerID' => $goal['scorer'],
						'period' => $goal['period'],
						'eventType' => $goal['type'],
						'eventTime' => $goalTime,
					)
				);
				if ($goal['assist1'] > 0) {
					$db->insert('event',
						array(
							'gameID' => $gameID,
							'playerID' => $goal['assist1'],
							'period' => $goal['period'],
							'eventType' => 'assist1',
							'eventTime' => $goalTime,
						)
					);
				}
				if ($goal['assist2'] > 0) {
					$db->insert('event',
						array(
							'gameID' => $gameID,
							'playerID' => $goal['assist2'],
							'period' => $goal['period'],
							'eventType' => 'assist2',
							'eventTime' => $goalTime,
						)
					);
				}
			}

			foreach ($_POST['pens'] as $pen) {
				if (strlen($pen['period']) < 1) continue;
				$gMin = 15 - $pen['clockMin'];
				$gSec = 60 - $pen['clockSec'];
				$penTime = ($gMin * 60) + ($gSec) - 60;
				$teamID = (isset($pen['home'])) ? $_POST['homeTeamID'] : $_POST['awayTeamID'];
				$db->insert('event',
					array(
						'gameID' => $gameID,
						'playerID' => $pen['player'],
						'period' => $pen['period'],
						'penalty' => $pen['penalty'],
						'penaltyMinutes' => $pen['minutes'],
						'eventType' => $pen['type'],
						'eventTime' => $penTime,
					)
				);
			}
			break;
	}
	print json_encode(array('mode'=>$_POST['mode'], 'data'=>$return));
	exit();
}

//BEGIN POST PROCESSING CODE
$seasonSelect = '';
$db->query("SELECT * FROM season order by seasonStart DESC");
while ($row = $db->fetch_assoc()) {
	$seasonSelect .= "<option value='$row[seasonID]'>$row[seasonName]</option>";
}

$teamSelect = '<option value="">Select Team</option>';
$db->query("SELECT * FROM team");
while ($row = $db->fetch_assoc()) {
	$teamSelect .= "<option value='$row[teamID]'>$row[teamName]</option>";
}

$gameSelect = '<option value="">Select Game</option>';
$db->query("SELECT * FROM game");
while ($row = $db->fetch_assoc()) {
	$theDate = date('Y-m-d h:i a', $row['gameTime']);
	$gameSelect .= "<option value='$row[gameID]'>$theDate</option>";
}

$playerSelect = '<option value="">Select Player</option>';
$db->query("SELECT * FROM player LEFT JOIN team USING (teamID) ORDER BY teamID, CAST(number as unsigned), firstname, lastname");
while ($row = $db->fetch_assoc()) {
	$nick = strlen($row['nickName']) ? "\"$row[nickName]\" " : '';
	$thePlayer = "$row[number] $row[firstName] $nick$row[lastName] ($row[teamName])";
	$playerSelect .= "<option teamID='$row[teamID]' value='$row[playerID]'>$thePlayer</option>";
}

$playerList = '';
$db->query("SELECT * FROM player LEFT JOIN team USING (teamID) ORDER BY teamID, CAST(number as unsigned), firstname, lastname");
while ($row = $db->fetch_assoc()) {
	$nick = strlen($row['nickName']) ? "\"$row[nickName]\" " : '';
	$thePlayer = "$row[number] $row[firstName] $nick$row[lastName] ($row[teamName])";
	$playerList .= "$thePlayer<br>";
}

$homeGoalRows = '';
$awayGoalRows = '';
$count = 1;
for ($ii = 1; $ii <= 20; $ii++) {
	$theVar = ($ii < 10) ? 'home' : 'away';
	$xx = $theVar.'GoalRows';
	$$xx .= <<<ROWEND
		<tr>
			<td><input type="hidden" name="goals[$count][$theVar]" value="yes"><input name="goals[$count][period]" size="3" type="text"></td>
			<td><input name="goals[$count][clockMin]" size="3" placeholder="Min" type="text"> : <input name="goals[$count][clockSec]" size="3" placeholder="Sec"></td>
			<td><select name="goals[$count][type]"><option value="goal">EV</option><option value="ppgoal">PP</option><option value="engoal">EN</option><option value="shgoal">SH</option></select></td>
			<td><select class="playerSelect" name="goals[$count][scorer]">$playerSelect</select></td>
			<td><select class="playerSelect" name="goals[$count][assist1]">$playerSelect</select></td>
			<td><select class="playerSelect" name="goals[$count][assist2]">$playerSelect</select></td>
		</tr>
ROWEND;
	$count++;
}

$homePenRows = '';
$awayPenRows = '';
$count = 1;
for ($ii = 1; $ii <= 20; $ii++) {
	$theVar = ($ii < 10) ? 'home' : 'away';
	$xx = $theVar.'PenRows';
	$$xx .= <<<ROWEND
		<tr>
			<td><input type="hidden" name="pens[$count][$theVar] value="yes"><input name="pens[$count][period]" size="3" type="text"></td>
			<td><input name="pens[$count][clockMin]" size="3" placeholder="Min" type="text"> : <input name="pens[$count][clockSec]" size="3" placeholder="Sec"></td>
			<td><input name="pens[$count][penalty]" type="text"></td>
			<td><select name="pens[$count][type]"><option value="minorpen">Minor</option><option value="majorpen">Major</option><option value="miscon">Misconduct</option><option value="gmmiscon">Game Misconduct</option></select></td>
			<td><select class="playerSelect" name="pens[$count][player]">$playerSelect</select></td>
			<td><input name="pens[$count][minutes]" size="3" type="text"></td>
		</tr>
ROWEND;
	$count++;
}

$_HEADER['pageJS'] = <<<JSEND
	$(document).ready(function() {
		$('form').submit(function(e) {
			var items = $(this).serialize();
			$.post('manage.php', items, function(data) {
				if (data.mode == 'season') {
					$('select.seasonSelect').each(function(ii, obj) {
						var curSel = $(obj).val();
						$(obj).html(data.data);
						$(obj).val(curSel);
					});
					$('form#addSeason')[0].reset();
					alert('Season Added');
				}
				if (data.mode == 'team') {
					$('select.teamSelect').each(function(ii, obj) {
						var curSel = $(obj).val();
						$(obj).html(data.data);
						$(obj).val(curSel);
					});
					$('form#addTeam')[0].reset();
					alert('Team Added');
				}
				if (data.mode == 'player') {
					$('select.playerSelect').each(function(ii, obj) {
						var curSel = $(obj).val();
						$(obj).html(data.data.selects);
						$(obj).val(curSel);
					});
					$('#playerList').html(data.data.list);
					$('form#addPlayer [type=text]').val('');
					$('form#addPlayer [name=number]').focus();
				}
				if (data.mode == 'game') {
					$('form#addGame')[0].reset();
					alert('Game Added');
				}
				if (data.mode == 'updatestats') {
					alert('Game Added');
				}
			}, 'json');
			e.preventDefault();
		});
	});
JSEND;

require_once('../include/header.inc.php');
print <<<PAGEEND
<div style="position: fixed; right: 10px; top: 10px;">
<h2>Add Team</h2>
<form id="addTeam" method="post">
<input type="hidden" name="mode" value="team">
<table>
	<tr>
		<td>Name:</td>
		<td><input name="teamName" type="text"></td>
	</tr>
	<tr>
		<td colspan="2"><input type="submit"></td>
	</tr>
</table>
</form>

<h2>Add Player</h2>
<form id="addPlayer" method="post">
<input type="hidden" name="mode" value="player">
<table>
	<tr>
		<td>Team:</td>
		<td><select class="teamSelect" name="teamID">$teamSelect</select></td>
	</tr>
	<tr>
		<td>Number:</td>
		<td><input name="number" type="text"></td>
	</tr>
	<tr>
		<td>First Name:</td>
		<td><input name="firstName" type="text"></td>
	</tr>
	<tr>
		<td>Last Name:</td>
		<td><input name="lastName" type="text"></td>
	</tr>
	<tr>
		<td>Nick Name:</td>
		<td><input name="nickName" type="text"></td>
	</tr>
	<tr>
		<td colspan="2"><input type="submit"></td>
	</tr>
</table>
</form>
<div id="playerList">
$playerList
</div>
</div>

<h2>Add Game</h2>
<form id="addGame" method="post" action="add.php">
<input type="hidden" name="mode" value="game">
<table>
	<tr>
		<td>Season:</td>
		<td><select class="seasonSelect" name="seasonID">$seasonSelect</select></td>
	</tr>
	<tr>
		<td>Date (YYYY-MM-DD):</td>
		<td><input name="date" type="text"></td>
	</tr>
	<tr>
		<td>Time (HH-mm):</td>
		<td><input name="time" type="text"></td>
	</tr>
	<tr>
		<td>Home Team:</td>
		<td><select class="teamSelect" name="homeTeamID">$teamSelect</select></td>
	</tr>
	<tr>
		<td>Away Team:</td>
		<td><select class="teamSelect" name="awayTeamID">$teamSelect</select></td>
	</tr>
	<tr>
		<td>Home Shots:</td>
		<td><input placeholder="p1" name="homeShots[1]" size="3" type="text">
		<input placeholder="p2" name="homeShots[2]" size="3" type="text">
		<input placeholder="p3" name="homeShots[3]" size="3" type="text">
		<input placeholder="ot" name="homeShots[4]" size="3" type="text">
		<input placeholder="ot2" name="homeShots[5]" size="3" type="text"></td>
	</tr>
	<tr>
		<td>Away Shots:</td>
		<td><input placeholder="p1" name="awayShots[1]" size="3" type="text">
		<input placeholder="p2" name="awayShots[2]" size="3" type="text">
		<input placeholder="p3" name="awayShots[3]" size="3" type="text">
		<input placeholder="ot" name="awayShots[4]" size="3" type="text">
		<input placeholder="ot2" name="awayShots[5]" size="3" type="text"></td>
	</tr>
	<tr>
		<td>Notes:</td>
		<td><input name="notes" type="text"></td>
	</tr>
	<tr>
		<td>Home Goalie:</td>
		<td><select class="playerSelect" name="homeGoalie">$playerSelect</select></td>
	</tr>
	<tr>
		<td>Away Goalie:</td>
		<td><select class="playerSelect" name="awayGoalie">$playerSelect</select></td>
	</tr>
	<tr>
		<td>Home Goals:</td>
		<td>
			<table>
				<tr>
					<td>Period:</td>
					<td>Clock Time:</td>
					<td>Type:</td>
					<td>Scorer:</td>
					<td>Assist 1:</td>
					<td>Assist 2:</td>
				</tr>
				$homeGoalRows
			</table>
		</td>
	</tr>
	<tr>
		<td>Away Goals:</td>
		<td>
			<table>
				<tr>
					<td>Period:</td>
					<td>Clock Time:</td>
					<td>Type:</td>
					<td>Scorer:</td>
					<td>Assist 1:</td>
					<td>Assist 2:</td>
				</tr>
				$awayGoalRows
			</table>
		</td>
	</tr>
	<tr>
		<td>Home Penalties:</td>
		<td>
			<table>
				<tr>
					<td>Period:</td>
					<td>Clock Time:</td>
					<td>Infraction:</td>
					<td>Type:</td>
					<td>Player:</td>
					<td>Minutes:</td>
				</tr>
				$homePenRows
			</table>
		</td>
	</tr>
	<tr>
		<td>Away Penalties:</td>
		<td>
			<table>
				<tr>
					<td>Period:</td>
					<td>Clock Time:</td>
					<td>Infraction:</td>
					<td>Type:</td>
					<td>Player:</td>
					<td>Minutes:</td>
				</tr>
				$awayPenRows
			</table>
		</td>
	</tr>
	<tr>
		<td colspan="2"><input type="submit"></td>
	</tr>
</table>
</form>

<h2>Add Season</h2>
<form id="addSeason" method="post">
<input type="hidden" name="mode" value="season">
<table>
	<tr>
		<td>Name:</td>
		<td><input name="seasonName" type="text"></td>
	</tr>
	<tr>
		<td>Start Date (YYYY-MM-DD):</td>
		<td><input name="date" type="text"></td>
	</tr>
	<tr>
		<td colspan="2"><input type="submit"></td>
	</tr>
</table>
</form>

<form id="updateStats" method="post">
<input type="hidden" name="mode" value="updatestats">
<input value="Update Stats Manually" type="submit">
</form>
PAGEEND;
require_once('../include/footer.inc.php');
?>

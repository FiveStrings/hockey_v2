<?php
require_once('../include/config.inc.php');
if (!isset($_GET['gameID'])) die('No game ID');

$db->query("SELECT game.*, hometeam.teamName homeTeam, awayteam.teamName awayTeam FROM game JOIN team hometeam on (hometeam.teamID = homeTeamID) JOIN team awayteam on (awayteam.teamID = awayTeamID) WHERE game.gameID = ?", array($_GET['gameID']));
$gameInfo = $db->fetch_assoc();

$db->query('SELECT * FROM shot WHERE gameID = ?', array($_GET['gameID']));
while ($row = $db->fetch_assoc()) {
	$theVar = ($row['teamID'] == $gameInfo['homeTeamID']) ? 'homeShots' : 'awayShots';
	$gameInfo[$theVar][$row['period']] = $row['shots'];
	if (!isset($gameInfo[$theVar]['T'])) $gameInfo[$theVar]['T'] = 0;
	$gameInfo[$theVar]['T'] += $row['shots'];
}
$gameTime = date('Y-m-d h:i:s a', $gameInfo['gameTime']);

$homePenaltyRows = $awayPenaltyRows = $homePlayerRows = $awayPlayerRows = $homeScoringRows = $awayScoringRows = '';
$byPeriod = array(
	'homeScoringRows' => array('1'=>0, '2'=>0, '3'=>0, 'total'=>0),
	'awayScoringRows' => array('1'=>0, '2'=>0, '3'=>0, 'total'=>0),
);
$goals = array();
$penalties = array();
$playerStats = array();
$goalTypes = array('goal'=>'EV', 'ppgoal'=>'PP', 'shgoal'=>'SH', 'engoal'=>'EN');
$db->query('SELECT * FROM event JOIN player USING (playerID) WHERE gameID = ? ORDER BY period ASC, eventTime ASC', array($_GET['gameID']));
while ($row = $db->fetch_assoc()) {
	$teamVar = ($row['teamID'] == $gameInfo['homeTeamID']) ? 'home' : 'away';
	$rowKey = "$row[period]-$row[eventTime]";
	$playerKey = str_pad($row['number'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($row['playerID'], 3, '0', STR_PAD_LEFT);
	if (!isset($playerStats[$teamVar][$playerKey])) $playerStats[$teamVar][$playerKey] = array_merge($row, array('G' => 0, 'A' => 0, 'PTS' => 0, 'PIM' => 0));
	switch ($row['eventType']) {
		case 'goal':
		case 'ppgoal':
		case 'shgoal':
		case 'engoal':
			if (!isset($goals[$teamVar][$rowKey])) $goals[$teamVar][$rowKey] = $row;
			$goals[$teamVar][$rowKey]['eventType'] = $row['eventType'];
			$goals[$teamVar][$rowKey]['scorer'] = $row;
			$playerStats[$teamVar][$playerKey]['G']++;
			$playerStats[$teamVar][$playerKey]['PTS']++;
			break;
		case 'assist1':
			if (!isset($goals[$teamVar][$rowKey])) $goals[$teamVar][$rowKey] = $row;
			$goals[$teamVar][$rowKey]['assist1'] = $row;
			$playerStats[$teamVar][$playerKey]['A']++;
			$playerStats[$teamVar][$playerKey]['PTS']++;
			break;
		case 'assist2':
			if (!isset($goals[$teamVar][$rowKey])) $goals[$teamVar][$rowKey] = $row;
			$goals[$teamVar][$rowKey]['assist2'] = $row;
			$playerStats[$teamVar][$playerKey]['A']++;
			$playerStats[$teamVar][$playerKey]['PTS']++;
			break;
		case 'minorpen':
		case 'majorpen':
		case 'miscon':
		case 'gmmiscon':
			$penalties[$teamVar][$rowKey] = $row;
			$playerStats[$teamVar][$playerKey]['PIM']+=$row['penaltyMinutes'];
			break;
	}
}

		//print "<pre>";
		//print_r($goals);
		//print "</pre>";

foreach ($goals as $homeAway=>$info) {
	$theVar = "{$homeAway}ScoringRows";
	foreach ($info as $row) {
		$goalTime = gmdate('i:s', $row['eventTime']);
		$gInfo = array();

		//print "<pre>";
		//print_r($row);
		//print "</pre>";

		foreach (array('scorer', 'assist1', 'assist2') as $sType) {
			if (isset($row[$sType])) {
				$gInfo[$sType]['name'] = strlen($row[$sType]['firstName']) ? ($row[$sType]['firstName'] . (isset($row[$sType]['lastName']) ? ' '.$row[$sType]['lastName'][0].'.' : '')) : '';
				$gInfo[$sType]['number'] = $row[$sType]['number'];
				$gInfo[$sType]['title'] = strlen($gInfo[$sType]['name']) ? " title='{$gInfo[$sType]['name']}'" : '';
			} else {
				$gInfo[$sType]['name'] = '?';
				$gInfo[$sType]['number'] = '-';
				$gInfo[$sType]['title'] = '';
			}
		}

		//print "<pre>";
		//print_r($gInfo);
		//print "</pre>";

		$byPeriod[$theVar][$row['period']]++;
		$byPeriod[$theVar]['total']++;
		$$theVar .= <<<ROWEND
			<tr>
				<td class="small">$row[period]</td>
				<td class="medium">$goalTime</td>
				<td class="medium">{$goalTypes[$row['eventType']]}</td>
				<td class="small"{$gInfo['scorer']['title']}>{$gInfo['scorer']['number']}</td>
				<td class="small"{$gInfo['assist1']['title']}>{$gInfo['assist1']['number']}</td>
				<td class="small"{$gInfo['assist2']['title']}>{$gInfo['assist2']['number']}</td>
			</tr>
ROWEND;
	}
}

foreach ($penalties as $homeAway=>$info) {
	$theVar = "{$homeAway}PenaltyRows";
	foreach ($info as $row) {
		$penaltyTime = gmdate('i:s', $row['eventTime']);
		$playerName = strlen($row['firstName']) ? ($row['firstName'] . (isset($row['lastName']) ? ' '.$row['lastName'][0].'.' : '')) : '';
		$playerNumber = $row['number'];
		$playerTitle = strlen($playerName) ? " title='{$playerName}'" : '';

		$$theVar .= <<<ROWEND
		<tr>
			<td class="small">$row[period]</td>
			<td class="medium">$penaltyTime</td>
			<td class="small"$playerTitle>$playerNumber</td>
			<td class="large">$row[penalty]</td>
			<td class="small">$row[penaltyMinutes]</td>
		</tr>
ROWEND;
	}
}

/*
$players = array();
$q = $db->query("SELECT * FROM game_goal WHERE gameID = $_GET[gameID]");
if (!$q) printf("Error: %s\n", $db->error);
while ($row = $q->fetch_assoc()) {
	if (strlen($row['scorer'])) {
		@$players[$row['teamID']][$row['scorer']]['goals'] += 1;
		@$players[$row['teamID']][$row['scorer']]['points'] += 1;
	}
	if (strlen($row['assist1'])) {
		@$players[$row['teamID']][$row['assist1']]['assists'] += 1;
		@$players[$row['teamID']][$row['assist1']]['points'] += 1;
	}
	if (strlen($row['assist2'])) {
		@$players[$row['teamID']][$row['assist2']]['assists'] += 1;
		@$players[$row['teamID']][$row['assist2']]['points'] += 1;
	}
}
$q = $db->query("SELECT * FROM game_penalty WHERE gameID = $_GET[gameID]");
if (!$q) printf("Error: %s\n", $db->error);
while ($row = $q->fetch_assoc()) {
	@$players[$row['teamID']][$row['player']]['pim'] += $row['minutes'];
}

if (isset($_GET['debug'])) {
	print "<pre>";
	print_r($players);
	print "</pre>";
}

*/

foreach ($playerStats as $homeAway=>$info) {
	ksort($info);
	$theVar = "{$homeAway}PlayerRows";
	foreach ($info as $player) {
		$playerName = strlen($player['firstName']) ? ($player['firstName'] . (isset($player['lastName']) ? ' '.$player['lastName'][0].'.' : '')) : '';
		$theLink = ($player['teamID'] == 1) ? "<a href='player.php?playerID=$player[playerID]'>$player[number]</a>" : $player['number'];
		$$theVar .= <<<ROWEND
			<tr>
				<td class="small">$theLink</td>
				<td class="center">$playerName</td>
				<td class="small">$player[G]</td>
				<td class="small">$player[A]</td>
				<td class="small">$player[PTS]</td>
				<td class="small">$player[PIM]</td>
			</tr>
ROWEND;
	}
}

$db->query('SELECT * FROM player WHERE playerID IN (?, ?)', array($gameInfo['homeGoalieID'], $gameInfo['awayGoalieID']));
while ($row = $db->fetch_assoc()) {
	if ($row['playerID'] == $gameInfo['homeGoalieID']) {
		$homeGoalieName = strlen($row['firstName']) ? ($row['firstName'] . (isset($row['lastName']) ? ' '.$row['lastName'][0].'.' : '')) : '';
		$homeGoalieNumber = $row['number'];
		$homeGA = $byPeriod['awayScoringRows']['total'];
		$homeSA = $gameInfo['awayShots']['T'];
		$homeSV = $homeSA - $homeGA;
		$homeSVP = number_format($homeSV / $homeSA, 3);
	} else {
		$awayGoalieName = strlen($row['firstName']) ? ($row['firstName'] . (isset($row['lastName']) ? ' '.$row['lastName'][0].'.' : '')) : '';
		$awayGoalieNumber = $row['number'];
		$awayGA = $byPeriod['homeScoringRows']['total'];
		$awaySA = $gameInfo['homeShots']['T'];
		$awaySV = $awaySA - $awayGA;
		$awaySVP = number_format($awaySV / $awaySA, 3);
	}
}
print <<<PAGEEND
<html>
<head>
<style type="text/css">
	table {
		border-collapse: collapse;
		width: 101%;
		margin: -1px;
	}

	table#outer {
		width: 910px;
		border: 1px solid black;
	}

	table#outer > tbody > tr > td {
		border: 3px solid black;
		padding: 0;
	}

	td.small {
		width: 37px;
		text-align: center;
	}

	td.medium {
		width: 70px;
		text-align: center;
	}

	td.large {
		width: 120px;
		text-align: center;
	}

	table td {
		vertical-align: top;
		margin: 0;
		padding: 2px;
		border: 1px solid black;
		text-align: center;
	}
	.center { text-align: center; }
	.bold { font-weight: bold; }
	.border { border: 1px solid black; }
	.tdborders > tbody > tr > td { border: 1px solid black; }
</style>
</head>
<body>
<a href="index.php">Back to Home</a>
<table id="outer" cellpadding="0" cellspacing="0">
	<tr>
		<td class="center" style="font-size: 18pt;" colspan="3">$gameInfo[awayTeam] @ $gameInfo[homeTeam] on $gameTime</td>
	</tr>
	<tr>
		<td style="width: 33%" id="homeScoring">
			<table cellpadding="0" cellspacing="0">
				<tr class="bold"><td colspan="6" class="center">$gameInfo[homeTeam] Scoring</td></tr>
				<tr class="bold">
					<td class="small">Per</td>
					<td class="medium">Time</td>
					<td class="medium">Type</td>
					<td class="small">G</td>
					<td class="small">A</td>
					<td class="small">A</td>
				</tr>
				$homeScoringRows
			</table>
		</td>
		<td style="width: 34%">
			<table cellpadding="0" cellspacing="0">
				<tr class="bold">
					<td class="center" colspan="3">Scoring By Period</td>
				</tr>
				<tr class="bold">
					<td class="medium">Period</td>
					<td class="large">$gameInfo[homeTeam]</td>
					<td class="large">$gameInfo[awayTeam]</td>
				</tr>
				<tr>
					<td class="medium">1</td>
					<td class="large">{$byPeriod['homeScoringRows'][1]}</td>
					<td class="large">{$byPeriod['awayScoringRows'][1]}</td>
				</tr>
				<tr>
					<td class="medium">2</td>
					<td class="large">{$byPeriod['homeScoringRows'][2]}</td>
					<td class="large">{$byPeriod['awayScoringRows'][2]}</td>
				</tr>
				<tr>
					<td class="medium">3</td>
					<td class="large">{$byPeriod['homeScoringRows'][3]}</td>
					<td class="large">{$byPeriod['awayScoringRows'][3]}</td>
				</tr>
				<tr class="bold">
					<td class="medium">Final</td>
					<td class="large">{$byPeriod['homeScoringRows']['total']}</td>
					<td class="large">{$byPeriod['awayScoringRows']['total']}</td>
				</tr>
			</table>
		</td>
		<td style="width: 33%">
			<table cellpadding="0" cellspacing="0">
				<tr class="bold"><td colspan="6">$gameInfo[awayTeam] Scoring</td></tr>
				<tr class="bold">
					<td class="small">Per</td>
					<td class="medium">Time</td>
					<td class="medium">Type</td>
					<td class="small">G</td>
					<td class="small">A</td>
					<td class="small">A</td>
				</tr>
				$awayScoringRows
			</table>
		</td>
	</tr>
	<tr>
		<td style="width: 33%">
			<table cellpadding="0" cellspacing="0">
				<tr class="bold"><td colspan="6">$gameInfo[homeTeam] Penalties</td></tr>
				<tr class="bold">
					<td class="small">Per</td>
					<td class="medium">Time</td>
					<td class="small">#</td>
					<td class="center">Penalty</td>
					<td class="small">Min</td>
				</tr>
				$homePenaltyRows
			</table>
		</td>
		<td style="width: 34%">
			<table cellpadding="0" cellspacing="0">
				<tr class="bold">
					<td colspan="3">Shots By Period</td>
				</tr>
				<tr class="bold">
					<td class="medium">Period</td>
					<td class="large">$gameInfo[homeTeam]</td>
					<td class="large">$gameInfo[awayTeam]</td>
				</tr>
				<tr>
					<td class="medium">1</td>
					<td class="large">{$gameInfo['homeShots'][1]}</td>
					<td class="large">{$gameInfo['awayShots'][1]}</td>
				</tr>
				<tr>
					<td class="medium">2</td>
					<td class="large">{$gameInfo['homeShots'][2]}</td>
					<td class="large">{$gameInfo['awayShots'][2]}</td>
				</tr>
				<tr>
					<td class="medium">3</td>
					<td class="large">{$gameInfo['homeShots'][3]}</td>
					<td class="large">{$gameInfo['awayShots'][3]}</td>
				</tr>
				<tr class="bold">
					<td class="medium">Final</td>
					<td class="large">{$gameInfo['homeShots']['T']}</td>
					<td class="large">{$gameInfo['awayShots']['T']}</td>
				</tr>
			</table>
		</td>
		<td style="width: 33%">
			<table cellpadding="0" cellspacing="0">
				<tr class="bold"><td colspan="6">$gameInfo[awayTeam] Penalties</td></tr>
				<tr class="bold">
					<td class="small">Per</td>
					<td class="medium">Time</td>
					<td class="small">#</td>
					<td class="center">Penalty</td>
					<td class="small">Min</td>
				</tr>
				$awayPenaltyRows
			</table>
		</td>
	</tr>
	<tr>
		<td colspan="3">
			<table id="outer" cellpadding="0" cellspacing="0">
				<tr>
					<td style="border: 0; width: 50%">
						<table cellpadding="0" cellspacing="0">
							<tr class="bold"><td colspan="6">$gameInfo[homeTeam] Players</td></tr>
							<tr class="bold">
								<td class="small">#</td>
								<td class="center">Name</td>
								<td class="small">G</td>
								<td class="small">A</td>
								<td class="small">PTS</td>
								<td class="small">PIM</td>
							</tr>
							$homePlayerRows
							<tr class="bold">
								<td class="center" colspan="2">Goalie</td>
								<td class="small">GA</td>
								<td class="small">SA</td>
								<td class="small">SV</td>
								<td class="small">SV%</td>
							</tr>
							<tr>
								<td>$homeGoalieNumber</td>
								<td>$homeGoalieName</td>
								<td>$homeGA</td>
								<td>$homeSA</td>
								<td>$homeSV</td>
								<td>$homeSVP</td>
							</tr>
						</table>
					</td>
					<td style="border: 0; border-left: 3px solid black; width: 50%">
						<table cellpadding="0" cellspacing="0" style="border-right: 0; width: 100%;">
							<tr class="bold"><td colspan="6">$gameInfo[awayTeam] Players</td></tr>
							<tr class="bold">
								<td class="small">#</td>
								<td class="center">Name</td>
								<td class="small">G</td>
								<td class="small">A</td>
								<td class="small">PTS</td>
								<td class="small">PIM</td>
							</tr>
							$awayPlayerRows
							<tr class="bold">
								<td class="center" colspan="2">Goalie</td>
								<td class="small">GA</td>
								<td class="small">SA</td>
								<td class="small">SV</td>
								<td class="small">SV%</td>
							</tr>
							<tr>
								<td>$awayGoalieNumber</td>
								<td>$awayGoalieName</td>
								<td>$awayGA</td>
								<td>$awaySA</td>
								<td>$awaySV</td>
								<td>$awaySVP</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
</table>
$gameInfo[notes]
<br><br><form action="feedback.php" method="post">Suggest a correction: <br><textarea name="correctionText" rows="5" cols="44" placeholder="type in the correction here"></textarea><br><input type="submit"></form>
</body>
</html>
PAGEEND;
?>

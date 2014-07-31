<?php

$pageJS = (isset($_HEADER['pageJS'])) ? '<script type="text/javascript">'.$_HEADER['pageJS'].'</script>' : '';
?>
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
</style>
<script src="//code.jquery.com/jquery-1.11.0.js"></script>
<?php print $pageJS; ?>
<title>Red Beards Stats System</title>
</head>
<body>
<h1><img src="logo.png" style="height: 50px; margin-right: 15px;">Red Beards Stats System - Summer 2014</h1>

<?php
$examples = array(
    'comments' => 'Komentáře',
    'variables' => 'Proměnné',
    'colors' => 'Barvy',
    'nesting' => 'Vnořování',
    'mixins' => 'Mixiny',
    'important' => 'Important',
    'grouping' => 'Seskupování',
    'strings' => 'Řetězce',
    'raw' => 'Surová hodnota',
    'gradients' => 'Přechody',
    'modernizr' => 'Modernizr',
    'conditions' => 'Podmínky',
    'selectors' => 'Selektory',
    'while' => 'While cyklus',
    'map' => 'Pole',
    'include' => 'Vkládání souborů',
    'font-face' => 'Fonty',
);
?>
<!DOCTYPE html>
<!--[if lte IE 7 ]><html lang="cs" class="no-js ie7"><![endif]-->
<!--[if IE 8 ]><html lang="cs" class="no-js ie8"><![endif]-->
<!--[if IE 9 ]><html lang="cs" class="no-js ie9"><![endif]-->
<!--[if IE 10 ]><html lang="cs" class="no-js ie10"><![endif]-->
<!--[if (gt IE 10)|!(IE) ]><!--><html lang="cs" class="no-js"><!--<![endif]-->
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title>IvoryStyleSheets</title>
    <meta name="author" content="Jáchym Toušek">
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>
<script>
$(function(){

    $('#submit').click(function(){
        $.ajax({
            url: "compile.php",
            type: "POST",
            data: {
                iss: $('#iss').val()
            },
            success: function(data) {
                $('#css').val(data);
            },
            error: function(xmlhttp, status, error) {
                $('#css').val(status);
            }
        });
    });

});
</script>
<style>
textarea {
    width: 1000px;
    height: 300px;
    display: block;
}
</style>
</head>
<body>

<h1>IvoryStyleSheets</h1>

<h2>Příklady</h2>

<menu>
<?php
foreach ($examples as $key => $value) {
    echo '<li><a href="?input=' . $key . '">' . $value . '</a></li>';
}
?>
</menu>

<h2>Compiler</h2>

<textarea id="iss">
<?php
if (isset($_POST['iss'])) {
    echo $_POST['iss'];
} else {
    echo file_get_contents(__DIR__ . '/examples/' . $_GET['input'] . '.iss');
}
?>
</textarea>
<input type="submit" value="compile" id="submit">
<textarea id="css">
<?php
if (isset($_GET['input'])) {
    require './compile.php';
}
?>
</textarea>

<body>
</html>

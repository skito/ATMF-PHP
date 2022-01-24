<?php

require_once(__DIR__.'/atmf/engine.php');

$atmf = new \ATMF\Engine();


/** ********************* **/
/** Configuration Options **/
/** ********************* **/

$atmf->SetTemplateDiscoveryPath(__DIR__.'/templates', ['html', 'tpl']); // Default: templates

$locale = isset($_GET['locale']) && in_array($_GET['locale'], ['en-US', 'bg-BG']) ? $_GET['locale'] : 'en-US';
$atmf->SetCultureFolder(__DIR__.'/culture'); //Default: culture
$atmf->SetCulture($locale); // Default: en-US

//$atmf->allowGlobals = true; // Default: FALSE. Be careful with that if set it to TRUE!



/** ****************************** **/
/** Using the global selector __() **/
/** ****************************** **/

// Assign some template manually
//__('#template page', file_get_contents(__DIR__.'templates/template.html'));

// Assign variables
__('$fullname', 'Advanced-Template-Markup-Format');
__('$shortname', 'ATMF');
__('$pagetitle', '{$fullname " (" $shortname ")"}');
__('$slogan', 'Cultural made easy!');
__('$userData', __escape('{$crossScripting}'));

/** ****************** **/


/** ************** **/
/** Using PHP code **/
/** ************** **/
$atmf->vars['slogan'] = 'Cultural made easy!';

// Assign some template manually
//$atmf->SetTemplate('header', file_get_contents(__DIR__.'templates/header.html'));

/** ************** **/



/** ******************************* **/
/** Read variables and translations **/
/** ******************************* **/

__('$fullname'); //Output: Advanced Template Markup Format
__('$pagetitle'); //Output: $fullname ($shortname)
__('@page.theFox 12 red'); //Output: The red fox made 12 steps

/** ******************************* **/

/** **************** **/
/** Some nested data **/
/** **************** **/

$authors = [
    [
        'firstName' => 'William',
        'lastName' => 'Shakespeare',
        'books' => ['Hamlet', 'Macbeth', 'Romeo and Juliet', 'The Tempest'],
        'soldOut' => false
    ],
    [
        'firstName' => 'William',
        'lastName' => 'Faulkner',
        'books' => ['Light in August', 'Sanctuary', 'A Fable', 'The Hamlet'],
        'soldOut' => false
    ],
    [
        'firstName' => 'Paulo',
        'lastName' => 'Coelho',
        'books' => ['The Alchemist', 'Eleven Minutes', 'The Zahir', 'Adultery '],
        'soldOut' => true
    ]
];

__('$authors', $authors);

/** ********************* **/
/** Configuration Options **/
/** ********************* **/

//$atmf->SetCultureFolder(__DIR__.'/culture'); //Default: culture
//$atmf->SetCulture('bg-BG'); // Default: en-US
//$atmf->allowGlobals = true; // Default: FALSE. Be careful with that if set it to TRUE!
//$atmf->DiscoverTemplates(__DIR__.'/templates', ['html', 'ptpl']); // Default: No auto discovery

//$locale = isset($_GET['locale']) && in_array($_GET['locale'], ['en-US', 'bg-BG']) ? $_GET['locale'] : 'en-US';
//$atmf->SetCulture($locale);
$atmf->Rend();
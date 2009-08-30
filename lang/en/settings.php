<?php
/**
 * English language file for config of ABC plugin
 *
 */
 
$lang['abcok']          = 'May ABC be embedded?';
$lang['displayType']    = 'How to display the output';
  $lang['displayType_o_0'] = 'image only';
  $lang['displayType_o_1'] = 'image linked to midi';
  $lang['displayType_o_2'] = 'image with list of abc, midi, ps/pdf';
$lang['displaySource']  = 'Shall the abc source be shown as well?';
$lang['displayErrorlog']= 'Shall the error logs be displayed? (only once when saved)';

$lang['abc2ps']         = 'Where to find abcm2ps (or abc2ps or jcabc2ps or jaabc2ps or yaps)';
$lang['abc2midi']       = 'Where to find abc2midi (optional if the output is "image only")';
$lang['ps2pdf']         = 'Where to find ps2pdf (optional, only needed if the output is "image with list of abc, midi, ps/pdf" and if a pdf file instead of a ps file shall be generated)';
$lang['abc2abc']        = 'Where to find abc2abc (leave blank for disallowing transposition)';
$lang['fmt']            = 'Where to find an abcm2ps format file (e.g. "foo.fmt") to format the PS/PDF file (optional)';

$lang['params4img']     = 'Parameters for abcm2ps when generating the image (optional, for experts only!)';
$lang['params4ps']      = 'Parameters for abcm2ps when generating the ps/pdf file (optional, for experts only!)';
$lang['mediaNS']        = 'Namespace for ABC files (leave blank for root)';


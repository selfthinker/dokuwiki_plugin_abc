<?php
/*
 * ABC plugin, configuration metadata
 *
 */

$meta['abcok']          = array('onoff');
$meta['displayType']    = array('multichoice','_choices' => array(0,1,2));
$meta['displaySource']  = array('onoff');
$meta['displayErrorlog']= array('onoff');

$meta['abc2ps']         = array('string');
$meta['abc2midi']       = array('string');
$meta['ps2pdf']         = array('string');
$meta['abc2abc']        = array('string');
$meta['fmt']            = array('string');

$meta['params4img']     = array('string');
$meta['params4ps']      = array('string');
$meta['mediaNS']        = array('string');

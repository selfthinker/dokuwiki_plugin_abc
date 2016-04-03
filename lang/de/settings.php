<?php
/**
 * German language file for config of ABC plugin
 *
 */

$lang['abcok']          = 'Soll ABC eingebunden werden?';
$lang['displayType']    = 'Art der Ausgabe';
  $lang['displayType_o_0'] = 'nur Bild';
  $lang['displayType_o_1'] = 'Bild zum Midi verlinkt';
  $lang['displayType_o_2'] = 'Bild mit Linkliste zu ABC, Midi, PS/PDF';
$lang['displaySource']  = 'Soll die ABC-Quelle auch angezeigt werden?';
$lang['displayErrorlog']= 'Soll das Fehlerlog angezeigt werden? (nur einmal beim Speichern)';

$lang['abc2ps']         = 'Pfad zu abcm2ps (oder abc2ps oder jcabc2ps oder jaabc2ps oder yaps)';
$lang['abc2midi']       = 'Pfad zu abc2midi (optional, wenn Anzeige "nur Bild" ist)';
$lang['ps2pdf']         = 'Pfad zu ps2pdf (optional, wird nur benötigt wenn Anzeige "Bild mit Linkliste zu ABC, Midi, PS/PDF" ist und wenn eine PDF- statt eine PS-Datei erzeugt werden soll)';
$lang['abc2abc']        = 'Pfad zu abc2abc (leer lassen zum Deaktivieren von Transpositionen)';
$lang['fmt']            = 'Pfad zur abcm2ps-Formatsdatei (z.B. "foo.fmt") um die PS/PDF-Datei zu formatieren (optional)';

$lang['params4img']     = 'Parameter für abcm2ps zum Erzeugen des Bildes (optional, nur für Experten!)';
$lang['params4ps']      = 'Parameter für abcm2ps zum Erzeugen der PS/PDF-Datei (optional, nur für Experten!)';
$lang['mediaNS']        = 'Namensraum für ABC-Dateien (leer lassen fürs Hauptverzeichnis)';

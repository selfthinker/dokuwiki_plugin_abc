<?php
/**
 * ABC Plugin (http://dokuwiki.org/plugin:abc)
 * for ABC notation (http://abcnotation.org.uk/)
 * in DokuWiki (http://dokuwiki.org/)
 *
 * @license     GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author      Anika Henke <anika@selfthinker.org>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_abc extends DokuWiki_Syntax_Plugin {

    function getType(){
        return 'protected';
    }
    function getPType(){
        return 'block';
    }
    function getSort(){
        return 192;
    }

    function connectTo($mode) {
        $this->Lexer->addEntryPattern('<abc(?=.*\x3C/abc\x3E)',$mode,'plugin_abc');
    }
    function postConnect() {
        $this->Lexer->addExitPattern('</abc>','plugin_abc');
    }


    function handle($match, $state, $pos, Doku_Handler $handler){
        if ( $state == DOKU_LEXER_UNMATCHED ) {
            $matches = preg_split('/>/u',$match,2);
            $matches[0] = trim($matches[0]);
            return array($matches[1],$matches[0]);
        }
        return true;
    }

    /**
     * Create output
     */
    function render($mode, Doku_Renderer $renderer, $data) {
        global $INFO;
        global $ACT;
        global $conf;
        if($mode == 'xhtml' && strlen($data[0]) > 1){
            $src = $data[0];
            $origSrc = $src;
            $trans = "0 ".$data[1]; // "0" includes the original key
            $debug = $conf['allowdebug'];

            $error = $this->_checkExecs();
            if($this->getConf('abcok') && (!$INFO['rev'] || ($INFO['rev'] && ($ACT=='preview'))) && !$error){
            //do not create/show files if an old revision is viewed, but always if the page is previewed and never when there is an error

                $entitiesFile = dirname(__FILE__).'/conf/entities.conf';
                if (@file_exists($entitiesFile)) {
                    $entities = confToHash($entitiesFile);
                    $src = strtr($src,$entities);
                }
                $fileBase = $this->_getFileBase($origSrc);
                $srcFile = $fileBase.'.abc';
                $srcChanged = (!file_exists($srcFile) || (file_exists($srcFile) && $src!=io_readFile($srcFile)));
                if ($srcChanged) io_saveFile($srcFile, $src);

                if ($this->getConf('abc2abc') && is_executable($this->getConf('abc2abc'))) {
                    $transSrc = $this->_getTransSrc($trans);
                    $transNew = $this->_getTransNew($fileBase, $transSrc);
                } else {
                    $transSrc = array(0);
                    $transNew = array();
                }
                $renderList = $srcChanged ? $transSrc : $transNew;
                if($debug || $_REQUEST['purge']) $renderList = $transSrc;

                // create files
                foreach ($renderList as $transMode) {
                    // if no transposition is allowed and the tune shall be transposed
                    // by 0 semitones (= not at all), then nothing is appended to the fileBase;
                    // else append the amount of semitiones to the fileBase
                    $curFileBase = ($transMode==0) ? $fileBase : $fileBase."_".$transMode;
                    $abcFile = $curFileBase.'.abc';
                    io_saveFile($abcFile, $src);

                    ob_start();

                    if ($transMode!=0) {
                        $this->_transpose($abcFile, $srcFile, $transMode);
                    }
                    $debugLog = $this->_createImgFile($abcFile, $curFileBase);

                    if ($this->getConf('displayType')==1 || $this->getConf('displayType')==2) {
                        $this->_createMidiFile($abcFile, $curFileBase);
                    }
                    if ($this->getConf('displayType')==2) {
                        $this->_createPsFile($abcFile, $curFileBase);
                        if ($this->getConf('ps2pdf')) {
                            $this->_createPdfFile($abcFile, $curFileBase);
                        }
                    }
                    $errorLog = ob_get_contents();
                    ob_end_clean();
                }
                if (($this->getConf('displayErrorlog') || $debug) && $errorLog) {
                    $errorLog = str_replace($this->_getAbc2psVersion(), "abc2ps", $errorLog);
                    //hide abc2ps version for security reasons
                    //TODO: hide lines starting with "writing MIDI file", "File", "Output written on", ... for boring reasons
                    msg(nl2br($errorLog), 2);
                }
                if ($debugLog) {
                    msg($debugLog);
                }
                // display files
                foreach ($transSrc as $transMode) {
                    $curFileBase = ($transMode==0) ? $fileBase : $fileBase."_".$transMode;
                    $renderer->doc .= $this->_showFiles($curFileBase);
                }

                // always have the abc source in the html source (for search engine optimization)
                // only per css visible when displaySource = 1
                if ($this->getConf('displaySource')) $visible = " visible";
                $renderer->doc .= '<div class="abc_src'.$visible.'">'.NL;
                $renderer->doc .= $renderer->file($origSrc);
                $renderer->doc .= '</div>'.NL;
            } else {
                if ($error && $this->getConf('abcok')) {
                    msg($error, -1);
                }
                $renderer->doc .= $renderer->file($origSrc);
            }
            return true;
        }
        return false;
    }

    /**
     * check if all needed programs are set, existent and executable
     */
    function _checkExecs() {
        global $conf;
        $error .= $this->_checkExec($this->getConf('abc2ps'), 'abc2ps');
        $error .= $this->_checkExec($conf['im_convert'], 'im_convert');
        if (($this->getConf('displayType')==1) || ($this->getConf('displayType')==2)) {
            $tmpError1 = $this->_checkExec($this->getConf('abc2midi'), 'abc2midi');
            if ($tmpError1) $error .= $tmpError1."If you do not wish to install it, you can change the 'displayType' to '0' ('image only').<br />";
        }
        if($this->getConf('ps2pdf') && ($this->getConf('displayType')==2)) {
            $tmpError2 = $this->_checkExec($this->getConf('ps2pdf'), 'ps2pdf');
            if ($tmpError2) $error .= $tmpError2."If you do not wish to install it, you can leave it blank and a ps file will be generated instead.<br />";
        }
        return $error;
    }
    /**
     * check if a program is set, existent and executable
     */
    function _checkExec($execFile, $execName) {
        if (!$execFile) {
            $error .= "The '<strong>".$execName."</strong>' config option is <strong>not set</strong>.<br />";
        } else if (!file_exists($execFile)) {
            $error .= "'".$execFile."' (<strong>".$execName."</strong>) is <strong>not existent</strong>.<br />";
        } else if (!is_executable($execFile)) {
            $error .= "'".$execFile."' (<strong>".$execName."</strong>) is <strong>not executable</strong>.<br />";
        }
        return $error;
    }

    /**
     * get to-be directory and filename (without extension)
     *
     * all files are stored in the media directory into 'plugin_abc/<namespaces>/'
     * and the filename is a mixture of abc-id and abc-title (e.g. 42_the_title.abc|...)
     *
     */
    function _getFileBase($src) {
        global $ID;
        global $ACT;
        global $conf;
        $mediadir = $conf['mediadir'];

        // where to store the abc media files
        $abcdir = $this->getConf('mediaNS') ? $mediadir.'/'.$this->getConf('mediaNS') : $mediadir;
        io_makeFileDir($abcdir);
        $fileDir = $abcdir.'/'.utf8_encodeFN(str_replace(':','/',getNS($ID)));

        // the abcID is what comes after the 'X:'
        preg_match("/\s?X\s?:(.*?)\n/s", $src, $matchesX);
        $abcID = preg_replace('/\s?X\s?:/', '', $matchesX[0]);
        // the abcTitle is what comes after the (first) 'T:'
        preg_match("/\s?T\s?:(.*?)\n/s", $src, $matchesT);
        $abcTitle = preg_replace('/\s?T\s?:/', '', $matchesT[0]);
        $fileName = cleanID($abcID."_".$abcTitle);

        // no double slash when in root namespace
        $slashStr = (getNS($ID)) ? "/" : "";
        // have different fileBase for previewing
        $previewPrefix = ($ACT!='preview') ? "" : "x";

        $fileBase = $fileDir.$slashStr.$previewPrefix.$fileName;
        // unfortunately abcm2ps seems not to be able to handle
        // file names (realpath) of more than 120 characters
        $realFileBaseLen = (strlen(fullpath($abcdir)) - strlen($abcdir)) + strlen($fileBase);
        $char_len = 114;
        if ($realFileBaseLen >= $char_len) {
            $truncLen = strlen($fileBase) + ($char_len - $realFileBaseLen);
            $fileBase = substr($fileBase, 0, $truncLen);
        }
        return $fileBase;
    }

    /**
     * get transposition parameters from the source into a reasonable array
     */
    function _getTransSrc($trans) {
        $transSrc = explode(" ", $trans);
        // the semitones to transpose have to be integers
        $transSrc = array_map("intval", $transSrc);
        // do not transpose by the same amount of semitones more than once
        $transSrc = array_unique($transSrc);
        // do not transpose higher or lower than 24 semitones
        $transSrc = array_filter($transSrc, create_function('$t', 'return($t<24 && $t >-24);'));
        // do not allow transposition into more than 8 keys
        array_splice($transSrc, 8);
        return $transSrc;
    }

    /**
     * get all new and old trans params
     * return the new params, delete the corresponding old files
     */
    function _getTransNew($fileBase, $transSrc) {
        // get all abc files belonging to the fileBase
        $filesArrABC = glob(dirname($fileBase)."/{".basename($fileBase)."*.abc}", GLOB_BRACE);
        $transFS = array(0); // always include the original key
        // get all numbers after the '_' and before the '.abc'
        foreach ($filesArrABC as $f) {
            $f = basename($f, ".abc");
            $tr = substr(strrchr($f,'_'),1);
            if (intval($tr)) $transFS[] = $tr;
        }

        $transNew = array_diff($transSrc, $transFS);
        $transOld = array_diff($transFS, $transSrc);

        // delete old transposed files
        foreach ($transOld as $o) {
            $filesArrAll = glob(dirname($fileBase)."/{".basename($fileBase)."_".$o."*}", GLOB_BRACE);
            foreach ($filesArrAll as $d) {
                unlink($d);
            }
        }
        return $transNew;
    }

    /**
     * transpose and create transposed abc
     */
    function _transpose($abcFile, $srcFile, $trans) {
        passthru(fullpath($this->getConf('abc2abc'))." $srcFile -e -t $trans > $abcFile");
    }

    /**
     * create img file
     */
    function _createImgFile($abcFile, $fileBase) {
        global $conf;
        $epsFile = $fileBase.'001.eps';
        $imgFile = $fileBase.'.png';
        $debug = $conf['allowdebug'];
        $debugOutput = '';

        // create eps file
        $epsCommand = fullpath($this->getConf('abc2ps'))." $abcFile ".$this->getConf('params4img')." -E -O $fileBase.";
        passthru($epsCommand." 2>&1");

        if($debug) {
            $debugOutput .= "<h3>Debug Info for $abcFile</h3>";
            $debugOutput .= "<p>EPS file '".$epsFile."'";
            $debugOutput .= file_exists($epsFile) ? " <strong>exists</strong>" : " <strong>does not exist</strong>";
            $debugOutput .= ", command used to create it:</p>";
            $debugOutput .= "<pre>".$epsCommand."</pre>";
        }

        // convert eps to png file
        $pngCommand = fullpath($conf['im_convert'])." $epsFile $imgFile";
        passthru($pngCommand);

        if($debug) {
            $debugOutput .= "<p>PNG file '".$imgFile."'";
            $debugOutput .= file_exists($imgFile) ? " <strong>exists</strong>" : " <strong>does not exist</strong>";
            $debugOutput .= ", command used to create it:</p>";
            $debugOutput .= "<pre>".$pngCommand."</pre>";
        } else {
            if(file_exists($epsFile)) unlink($epsFile);
        }

        return $debugOutput;
    }
    /**
     * create ps file
     */
    function _createPsFile($abcFile, $fileBase) {
        $psFile  = $fileBase.'.ps';
        $fmt = $this->getConf('fmt');
        $addFmt = ($fmt && file_exists($fmt)) ? " -F ".fullpath($fmt) : "";
        passthru(fullpath($this->getConf('abc2ps'))." $abcFile $addFmt ".$this->getConf('params4ps')." -O $psFile 2>&1");
    }
    /**
     * create pdf file
     */
    function _createPdfFile($abcFile, $fileBase) {
        $psFile  = $fileBase.'.ps';
        $pdfFile  = $fileBase.'.pdf';
        passthru(fullpath($this->getConf('ps2pdf'))." $psFile $pdfFile");
        if(file_exists($psFile)) unlink($psFile);
    }
    /**
     * create midi file
     */
    function _createMidiFile($abcFile, $fileBase) {
        $midFile = $fileBase.'.mid';
        passthru(fullpath($this->getConf('abc2midi'))." $abcFile -o $midFile");
    }
    /**
     * get abc2ps version
     */
    function _getAbc2psVersion() {
        ob_start();
        passthru(fullpath($this->getConf('abc2ps'))." -V 2>&1");
        $version = ob_get_contents();
        $version = explode("\n",$version);
        ob_end_clean();
        return $version[0];
    }

    /**
     * get file and check if it exists
     */
    function _getFile($fileBase, $ext) {
        $file = $fileBase.$ext;
        return (file_exists($file)) ? $file : 0;
    }

    /**
     * get ID that has to be called from fetch.php
     */
    function _getFileID($file) {
        global $ID;
        return (getNS($ID)) ? getNS($ID).":".basename($file) : basename($file);
    }

    /**
     * html for internal media
     */
    function _showFile($file) {
        if (!$file) {
            return "Error: The file could not be generated.";
        }

        $mediaNS = $this->getConf('mediaNS').":";
        $name = $this->_getFileID($file);
        $id = $mediaNS.$name;
        $url = ml($id, array('t' => time())); // add timestamp for cache busting
        list($ext, $mime) = mimetype($file, false);

        if(substr($mime, 0, 5) == 'image') {
            $imgSize = getimagesize($file);
            $imgSize = $imgSize[3];
            return '<img src="'.$url.'" '.$imgSize.' alt="" />';
        } else {
            $class = 'mediafile mf_'.preg_replace('/[^_\-a-z0-9]+/i', '_', $ext);
            return '<a href="'.$url.'" class="'.$class.'">'.$name.'</a>';
        }
    }


    /**
     * html for displaying all files; dependant on displayType
     */
    function _showFiles($fileBase) {
        $imgFile = $this->_getFile($fileBase, '.png');
        $midFile = $this->_getFile($fileBase, '.mid');
        $abcFile = $this->_getFile($fileBase, '.abc');
        $psFile  = $this->_getFile($fileBase, '.ps');
        $pdfFile = $this->_getFile($fileBase, '.pdf');
        $mediaNS = $this->getConf('mediaNS').":";
        $showImg = $this->_showFile($imgFile);

        switch ($this->getConf('displayType')) {
            // image only (case 0 and default)
            default:
            case 0:
                $display = '<p>'.$showImg.'</p>'.NL;
                break;

            // image linked to midi
            case 1:
                $display = $showImg;
                if($midFile) {
                    $display = '<a href="'.ml($mediaNS.$this->_getFileID($midFile)).'">'.$display.'</a>';
                }
                $display = '<p>'.$display.'</p>'.NL;
                break;

            // image with list of abc, midi, ps/pdf
            case 2:
                $display = '<ul>';
                // abc file is always there
                $display .= '<li>'.$this->_showFile($abcFile).'</li>';
                // midi file
                $display .= '<li>'.$this->_showFile($midFile).'</li>';
                // display pdf file if there is any, else display ps file
                if ($this->getConf('ps2pdf') && $pdfFile) {
                    $display .= '<li>'.$this->_showFile($pdfFile).'</li>';
                } else {
                    $display .= '<li>'.$this->_showFile($psFile).'</li>';
                }
                $display .= '</ul>'.NL;
                $display .= '<p>'.$showImg.'</p>'.NL;
                break;
        }
        $display = '<div class="abc">'.NL.$display.'</div>'.NL;
        return $display;
    }


}

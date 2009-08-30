<?php
/**
 * ABC Plugin (http://staffweb.cms.gre.ac.uk/~c.walshaw/abc/)
 * for DokuWiki (http://www.splitbrain.org/dokuwiki/wiki:dokuwiki)
 * 
 * todo ... maybe ...:
 *   * allow more parameters (eg. width)?
 *   * log abc2mps + abc2midi errors?
 *   * remove previewed files
 *   * show only links to transposed PNGs instead of displaying them?
 * 
 * @license     GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author      A.C. Henke <a.c.henke@arcor.de>
 * @version     2007-06-03
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_abc extends DokuWiki_Syntax_Plugin {

    var $_debug = 0;

    function getInfo(){
        return array(
            'author' => 'A.C. Henke',
            'email'  => 'a.c.henke@arcor.de',
            'date'   => '2007-06-03',
            'name'   => 'ABC Plugin',
            'desc'   => 'Displays sheet music (input ABC, output png with midi)',
            'url'    => 'http://wiki.splitbrain.org/plugin:abc',
        );
    }

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


    function handle($match, $state, $pos, &$handler){
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
    function render($mode, &$renderer, $data) {
        global $conf;
        global $INFO;
        if($mode == 'xhtml' && strlen($data[0]) > 1){
            $src = $data[0];
            $trans = "0 ".$data[1]; // "0" includes the original key

            $error = $this->_checkExecs();
            if($this->getConf('abcok') && !$INFO['rev'] && !$error){

                $origSrc = $src;
                $entitiesFile = dirname(__FILE__).'/conf/entities.conf';
                if (@file_exists($entitiesFile)) {
                    $entities = confToHash($entitiesFile);
                    $src = strtr($src,$entities);
                }
                $fileBase = $this->_getFileBase($conf['savedir'], $origSrc);
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
                if($this->_debug || $_REQUEST['purge']) $renderList = $transSrc;

                // create files
                foreach ($renderList as $transMode) {
                    // if no transposition is allowed and the tune shall be transposed
                    // by 0 semitones (= not at all), then nothing is appended to the fileBase;
                    // else append the amount of semitiones to the fileBase
                    $curFileBase = ($transMode==0) ? $fileBase : $fileBase."_".$transMode;
                    $abcFile = $curFileBase.'.abc';
                    io_saveFile($abcFile, $src);

                    if(!$this->_debug) ob_start();

                    if ($transMode!=0) {
                        $this->_transpose($abcFile, $srcFile, $transMode);
                    }
                    $this->_createImgFile($abcFile, $curFileBase);

                    if ($this->getConf('displayType')==1 || $this->getConf('displayType')==2) {
                        $this->_createMidiFile($abcFile, $curFileBase);
                    }
                    if ($this->getConf('displayType')==2) {
                        $this->_createPsFile($abcFile, $curFileBase);
                        if ($this->getConf('ps2pdf') && is_executable($this->getConf('ps2pdf'))) {
                            $this->_createPdfFile($abcFile, $curFileBase);
                        }
                    }
                    if(!$this->_debug) ob_end_clean();
                }
                // display files
                foreach ($transSrc as $transMode) {
                    $curFileBase = ($transMode==0) ? $fileBase : $fileBase."_".$transMode;
                    $renderer->doc .= $this->_showFiles($curFileBase);
                }

                // always have the abc source in the html source (for search engine optimization)
                // only per css visible when displaySource = 1
                if ($this->getConf('displaySource')) $visible = " visible";
                $renderer->doc .= "<div class=\"abc_src".$visible."\">";
                $renderer->doc .= $renderer->file($origSrc);
                $renderer->doc .= "</div>";
            } else {
                if ($error) print "<div class=\"error\">".$error."</div>";
                $renderer->doc .= $renderer->file($origSrc);
            }
            return true;
        }
        return false;
    }

    /**
     * check if all needed programs are executable
     */
    function _checkExecs() {
        global $conf;
        if (!is_executable($this->getConf('abc2ps'))) {
            $error .= $this->getConf('abc2ps')." (abc2ps) is not executable.<br />";
        }
        if (!is_executable($conf['im_convert'])) {
            $error .= $conf['im_convert']." (im_convert) is not executable.<br />";
        }
        if (($this->getConf('displayType')==1 || $this->getConf('displayType')==2) && !is_executable($this->getConf('abc2midi'))) {
            $error .= $this->getConf('abc2midi')." (abc2midi) is not executable.<br />If you do not want to install it, you can change the displayType to '0' ('image only').<br />";
        }
        if($this->getConf('ps2pdf') && ($this->getConf('displayType')==2) && !is_executable($this->getConf('ps2pdf'))) {
            $error .= $this->getConf('ps2pdf')." (ps2pdf) is not executable.<br />If you do not want to install it, you can leave it blank and a ps file will be generated instead.<br />";
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
    function _getFileBase($savedir, $src) {
        global $ID;
        global $ACT;

        // where to store the abc media files
        $abcdir = $savedir.'/media/plugin_abc';
        io_makeFileDir($abcdir);
        $fileDir = $abcdir.'/'.utf8_encodeFN(str_replace(':','/',getNS($ID)));

        // the abcID is what comes after the 'X:'
        preg_match("/\s?X\s?:(.*?)\n/se", $src, $matchesX);
        $abcID = preg_replace('/\s?X\s?:/', '', $matchesX[0]);
        // the abcTitle is what comes after the (first) 'T:'
        preg_match("/\s?T\s?:(.*?)\n/se", $src, $matchesT);
        $abcTitle = preg_replace('/\s?T\s?:/', '', $matchesT[0]);
        $fileName = cleanID($abcID."_".$abcTitle);

        // no double slash when in root namespace
        $slashStr = (getNS($ID)) ? "/" : "";
        // have different fileBase for previewing
        $previewPrefix = ($ACT!='preview') ? "" : "x";

        $fileBase = $fileDir.$slashStr.$previewPrefix.$fileName;
        // unfortunately abcm2ps seems not to be able to handle
        // file names (realpath) of more than 120 characters 
        $realFileBaseLen = (strlen(realpath($abcdir)) - strlen($abcdir)) + strlen($fileBase);
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
        passthru(realpath($this->getConf('abc2abc'))." $srcFile -e -t $trans > $abcFile");
    }

    /**
     * create img file
     */
    function _createImgFile($abcFile, $fileBase) {
        global $conf;
        $epsFile = $fileBase.'001.eps';
        $imgFile = $fileBase.'.png';

        // create eps file
        passthru(realpath($this->getConf('abc2ps'))." $abcFile ".$this->getConf('params4img')." -E -O $fileBase.");

        if($this->_debug) {
            echo "<h1>Debug Info for $abcFile</h1><pre>";
            echo "==== create eps:".NL."-> ".realpath($this->getConf('abc2ps'))." $abcFile ".$this->getConf('params4img')." -E -O $fileBase.".NL;
            if(file_exists($epsFile)) echo "eps file '".$epsFile."' EXISTS".NL;
            else echo "eps file '".$epsFile."' DOES NOT EXIST".NL;
        }

        // convert eps to png file
        passthru(realpath($conf['im_convert'])." $epsFile $imgFile");

        if($this->_debug) {
            echo NL."==== create png:".NL."-> ".realpath($conf['im_convert'])." $epsFile $imgFile".NL;
            if(file_exists($imgFile)) echo "img file '".$imgFile."' EXISTS".NL;
            else echo "img file '".$imgFile."' DOES NOT EXIST".NL;
            echo "</pre><hr />";
        } else {
            if(file_exists($epsFile)) unlink($epsFile);
        }

    }
    /**
     * create ps file
     */
    function _createPsFile($abcFile, $fileBase) {
        $psFile  = $fileBase.'.ps';
        $fmt = $this->getConf('fmt');
        $addFmt = ($fmt && file_exists($fmt)) ? " -F ".realpath($fmt) : "";
        passthru(realpath($this->getConf('abc2ps'))." $abcFile $addFmt ".$this->getConf('params4ps')." -O $psFile");
    }
    /**
     * create pdf file
     */
    function _createPdfFile($abcFile, $fileBase) {
        $psFile  = $fileBase.'.ps';
        $pdfFile  = $fileBase.'.pdf';
        passthru(realpath($this->getConf('ps2pdf'))." $psFile $pdfFile");
        if(file_exists($psFile)) unlink($psFile);
    }
    /**
     * create midi file
     */
    function _createMidiFile($abcFile, $fileBase) {
        $midFile = $fileBase.'.mid';
        passthru(realpath($this->getConf('abc2midi'))." $abcFile -o $midFile");
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
     * show image (or an error if it does not exist)
     */
    function _showImg($imgFile, $abcMediaUrl) {
        if($imgFile) {
            $imgSize = getimagesize($imgFile);
            $imgSize = $imgSize[3];
            return "<img src=\"".$abcMediaUrl.$this->_getFileID($imgFile)."\" $imgSize alt=\"\" />";
        } else {
            return "Error: The image could not be generated.";
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
        $display = "";
        $abcMediaUrl=DOKU_BASE."lib/exe/fetch.php?cache=cache&amp;media=plugin_abc:";
        $showImg = $this->_showImg($imgFile, $abcMediaUrl);

        switch ($this->getConf('displayType')) {
            // image only (case 0 and default)
            default:
            case 0:
                $display = $showImg;
                break;

            // image linked to midi
            case 1:
                $display = $showImg;
                if($midFile) {
                    $display = "<a href=\"".$abcMediaUrl.$this->_getFileID($midFile)."\">".$display."</a>";
                }
                break;

            // image with list of abc, midi, ps/pdf
            case 2:
                $display = "<ul>\n";
                // abc file is always there
                $display .= "<li><a href=\"".$abcMediaUrl.$this->_getFileID($abcFile)."\" class=\"media mediafile mf_abc\">".$this->_getFileID($abcFile)."</a></li>\n";
                // midi file
                $display .= $midFile ? "<li><a href=\"".$abcMediaUrl.$this->_getFileID($midFile)."\" class=\"media mediafile mf_mid\">".$this->_getFileID($midFile)."</a></li>\n" : "";
                // display pdf file if there is any, else display ps file
                if ($this->getConf('ps2pdf') && $pdfFile) {
                    $display .= "<li><a href=\"".$abcMediaUrl.$this->_getFileID($pdfFile)."\" class=\"media mediafile mf_pdf\">".$this->_getFileID($pdfFile)."</a></li>\n";
                } else {
                    $display .= $psFile ? "<li><a href=\"".$abcMediaUrl.$this->_getFileID($psFile)."\" class=\"media mediafile mf_ps\">".$this->_getFileID($psFile)."</a></li>\n" : "";
                }
                $display .= "</ul>\n";
                $display .= $showImg;
                break;

        }
        $display = "<div class=\"abc\">".$display."</div>";
        return $display;
    }


}

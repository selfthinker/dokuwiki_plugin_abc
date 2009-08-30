<?php
/**
 * ABC Plugin (http://staffweb.cms.gre.ac.uk/~c.walshaw/abc/)
 * for DokuWiki (http://www.splitbrain.org/dokuwiki/wiki:dokuwiki)
 * 
 * todo:
 *   * .trans file is not needed anymore with the trans number appended to each file
 *   * if transMode has changed, it is not necessary to parse *all* files again
 *   * remove transposed files when digit is deleted
 *   * remove previewed files
 * maybe ...
 *   * show only links to transposed PNGs instead of displaying them?
 *   * create pdfs?
 *   * allow more parameters (eg. width)?
 *   * log abc2mps + abc2midi errors?
 * 
 * @license		GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author		A.C. Henke <a.c.henke@arcor.de>
 * @version		2006-02-27a
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');


class syntax_plugin_abc extends DokuWiki_Syntax_Plugin {

	function getInfo(){
		return array(
			'author' => 'A.C. Henke',
			'email'  => 'a.c.henke@arcor.de',
			'date'   => '2006-02-27a',
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
		global $ID;
		global $ACT;
		global $INFO;
		if($mode == 'xhtml' && strlen($data[0]) > 1){
			$src = $data[0];
			if ($conf['abcABC']) {
				$trans = "0 ".$data[1];//"0" includes the original key
				$transArr = explode(" ", $trans);
				$transArr = array_map("intval", $transArr);//the semitones to transpose have to be integers
				$transArr = array_unique($transArr);//do not transpose by X semitones more than once
				array_splice($transArr, 8);//do not allow transposition into more than 8 keys
			} else {
				$transArr = array(0);
			}

			if($conf['abcok'] && !$INFO['rev']){
				$abcdir = $conf['savedir'].'/media/plugin_abc';//where to store the abc media files
				io_makeFileDir($abcdir);

				$fileBase = $abcdir.'/'.utf8_encodeFN(str_replace(':','/',getNS($ID)));
				/* titles or ids (X:) can be ambiguous, so the filename is a mixture of id and title like
				42_the_title.abc|... */
				if ($ACT!='preview') {
					$fileBase .= '/'.cleanID($this->_getAbcID($src)."_".$this->_getAbcTitle($src));
				} else {
					$fileBase .= '/x'.cleanID($this->_getAbcID($src)."_".$this->_getAbcTitle($src));
				}
				/* unfortunately abcm2ps seems not to be able to handle file names (realpath) of more than 120 characters */
				$realFileBaseLen = (strlen(realpath($abcdir)) - strlen($abcdir)) + strlen($fileBase);
				if ($realFileBaseLen >= 116) {
					$truncLen = strlen($fileBase) + (116 - $realFileBaseLen);
					$fileBase = substr($fileBase, 0, $truncLen);
				}

				$transFile = $fileBase.'.trans';
				if ($conf['abcABC'] && (!file_exists($transFile) || $trans!=io_readFile($transFile) )) {
					$this->_createTransFile($transFile, $trans);
					$transChanged = 1;
				}
				$srcFile = $fileBase.'.abc';
				if (file_exists($srcFile)) {
					$srcChanged = ($src!=io_readFile($srcFile));
				}
				if ($conf['abcABC'] && (!file_exists($srcFile) || $srcChanged)) {
					$this->_createAbcFile($srcFile, $src);
				}

				foreach ($transArr as $transMode) {
					if ($transMode<24 && $transMode>-24) {
						if (!$conf['abcABC'] || $transMode==0) {
							$curFileBase = $fileBase;
						} else {
							$curFileBase = $fileBase."_".$transMode;
						}
						$abcFile = $curFileBase.'.abc';
						if (!file_exists($abcFile) || $srcChanged || $transChanged) {
							$this->_createAbcFile($abcFile, $src);
							if ($conf['abcABC'] && $transMode!=0) {
								$this->_transpose($abcFile, $srcFile, $transMode);
							}
							$this->_createImgFile($abcFile, $curFileBase);

							if ($conf['abcDisplayType']==1 || $conf['abcDisplayType']==2) {
								$this->_createMidiFile($abcFile, $curFileBase);
							}
							if ($conf['abcDisplayType']==2) {
								$this->_createPsFile($abcFile, $curFileBase);
							}
						}
						$renderer->doc .= $this->_showFiles($curFileBase, $conf['abcDisplayType']);
					}
				}
			} else {
				$renderer->doc .= $renderer->file($src);
			}
			return true;
		}
		return false;
	}

	function _getAbcTitle ($src) {
		preg_match("/\s?T\s?:(.*?)\n/se", $src, $matchesT);
		$title = preg_replace('/\s?T\s?:/', '', $matchesT[0]);

		return $title;
	}
	function _getAbcID ($src) {
		preg_match("/\s?X\s?:(.*?)\n/se", $src, $matchesX);
		$id = preg_replace('/\s?X\s?:/', '', $matchesX[0]);

		return $id;
	}

	//transpose and create transposed abc
	function _transpose($abcFile, $srcFile, $trans) {
		global $conf;
		passthru(realpath($conf['abcABC'])." $srcFile -t $trans > $abcFile");
	}
	//store somewhere the amount of semitones to transpose
	function _createTransFile($transFile, $trans) {
		io_saveFile($transFile, $trans);
	}

	//create files (abc, img, ps, midi)
	function _createAbcFile($abcFile, $src) {
		io_saveFile($abcFile, $src);
	}
	function _createImgFile($abcFile, $fileBase) {
		global $conf;

		$epsFile = $fileBase.'001.eps';
		$imgFile = $fileBase.'.png';
		ob_start();
		passthru(realpath($conf['abcPS'])." $abcFile -s 1 -w 600 -E -O $fileBase.");
		//without a special width: passthru(realpath($conf['abcPS'])." $abcFile -s 1 -E -O $fileBase.");
		ob_end_clean();
		ob_start();
		passthru(realpath($conf['abcConvert'])." $epsFile $imgFile");
		ob_end_clean();
		if(file_exists($epsFile)) unlink($epsFile);
	}
	function _createPsFile($abcFile, $fileBase) {
		global $conf;
		$psFile  = $fileBase.'.ps';
		ob_start();
		if ($conf['abcFmtFile']) {
			$format = $conf['abcFmtFile']?"-F ".realpath($conf['abcFmtFile']):"-s 1";
			passthru(realpath($conf['abcPS'])." $abcFile $format -O $psFile");
		} else {
			passthru(realpath($conf['abcPS'])." $abcFile -O $psFile");
		}
		ob_end_clean();
	}
	function _createMidiFile($abcFile, $fileBase) {
		global $conf;
		$midFile = $fileBase.'.mid';
		ob_start();
		passthru(realpath($conf['abcMIDI'])." $abcFile -o $midFile");
		ob_end_clean();
	}

	//get files (abc, img, ps, midi)
	function _getFile($fileBase, $ext) {
		$file = $fileBase.$ext;
		return (file_exists($file)) ? $file : 0;
	}

	//get ID that has to be called from fetch.php
	function _getFileID($file) {
		global $ID;
		return (getNS($ID)) ? getNS($ID).":".substr(strrchr($file,'/'),1) : substr(strrchr($file,'/'),1);
	}


	//display on screen
	function _showFiles($fileBase, $displayType) {
		$imgFile = $this->_getFile($fileBase, '.png');
		$midFile = $this->_getFile($fileBase, '.mid');
		$abcFile = $this->_getFile($fileBase, '.abc');
		$psFile  = $this->_getFile($fileBase, '.ps');
		$display = "";
		$abcMediaUrl=DOKU_BASE."lib/exe/fetch.php?cache=cache&amp;media=plugin_abc:";

		if($imgFile) {
			$imgSize = getimagesize($imgFile);
			$imgSize = $imgSize[3];

			switch ($displayType) {
				case 1:	//image linked to midi
					$display = "<img src=\"".$abcMediaUrl.$this->_getFileID($imgFile)."\" $imgSize alt=\"\" />";
					if($midFile) {
						$display = "<a href=\"".$abcMediaUrl.$this->_getFileID($midFile)."\">".$display."</a>";
					}
					break;

				case 2: //image with list of abc, midi, ps
					$display = "<ul>\n";
					$display .= "<li><a href=\"".$abcMediaUrl.$this->_getFileID($abcFile)."\">".$this->_getFileID($abcFile)."</a></li>\n";
					$display .= $midFile?"<li><a href=\"".$abcMediaUrl.$this->_getFileID($midFile)."\">".$this->_getFileID($midFile)."</a></li>\n":"";
					$display .= $psFile?"<li><a href=\"".$abcMediaUrl.$this->_getFileID($psFile)."\">".$this->_getFileID($psFile)."</a></li>\n":"";
					$display .= "</ul>\n";
					$display .= "<img src=\"".$abcMediaUrl.$this->_getFileID($imgFile)."\" $imgSize alt=\"\" />\n";
					break;

				case 0: //image only (case 0 and default)
				default:
					$display = "<img src=\"".$abcMediaUrl.$this->_getFileID($imgFile)."\" $imgSize alt=\"\" />";
					break;
			}
			$display = "<div class=\"abc\">".$display."</div>";
		}
		return $display;
	}

}

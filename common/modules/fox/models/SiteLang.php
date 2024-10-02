<?php
// dsm 28 aug 24 : HankLang operations
// dsm 25 sep 24 : Totally forget what I was doing here. Wait for it.

namespace common\modules\api3\models;

use common\components\Dump;
use common\helpers\BHLogger;
use common\helpers\ReportCountHelper;
use common\modules\fox\models\HankLang;
use Yii;

class SiteLang extends HankLang {

	public $log = null;
	public $rpt = null;
	public $verbose = true;

	public function ready($fun){
		if ($this->log){
			return;
		}
		$fun='XLAT';
		$dtRun = date('Y-m-d-H-i-s');
		$this->log = new BHLogger(Yii::$app->params['LOCAL_LOG_PATH'] . $fun . $dtRun . '.log', $this->verbose);
		$this->log->put('BEGIN RUN ' . $dtRun);
		$this->rpt = new ReportCountHelper();
	}

	private function _db_backup(){
		/*
		 * Redacted
		 */
	}

	/*---------------------------------------------------------------------------------------------------------------*/

	// Generate BHSITE language files from HankLang table
	// per lang, output is simple javascript flat object (strings only)
	public function generate($path=false) {

		$this->ready('generate');

		$this->log->put('Generating master files...');

		if (!$path){
			$path = Yii::$app->params['BHSITE_PATH'] . '/i18n/lang/';   // master files
		}

		// For each language
		foreach ( self::LANGS as $iso ) {
			$this->outputLines($path,$iso);
		}
		echo 'cat ' . $this->log->fnLog . PHP_EOL;

		return true;   // No commands failed
	}

	public function outputLines($path,$iso){
		// Output a flat simple js object
		$dir = $path . SiteLang::fullISO( $iso ); //      /home/me/bhjs/BHSITE/src/i18n/lang/en-us/index.js
		if ( !is_dir($dir)){
			mkdir($dir);
		}
		$fnOut = $dir . '/index.js';

		$lines = $this->getLines($this->getQuery($iso));
		$buff = '// Generated ' . date( 'Y-m-d H:i:s' ) . PHP_EOL . 'module.exports = {' . PHP_EOL;
		$buff .= PHP_EOL . implode( ",\n\t", $lines ) . ",\n";
		$buff .= "}\n";

		// todo Not sure why rpt is null here. Something isn't doing what I think it is
		if ( $this->rpt){
			$this->rpt->add($fnOut, count($lines));
		}
		$this->log->put(count($lines) . ' written to ' . $fnOut);

		file_put_contents( $fnOut, $buff );
	}

	public function getQuery($iso){
		return HankLang::find()->where( [ 'iso' => $iso,  'active'=>1 ] )->orderby( [ 'xlat' => SORT_ASC ] );
	}

	public function getLines($Q) {
		// All phrases for this language
		$section = $Q->all();
		$lines = [];
		//$lastKey=false;                 // todo this is wrong, should not be.
		foreach ( $section as $row ) {
			// Eliminate dupe keys  (seen: "All","Normal" in core + profile) todo DO THIS AFTER WITH ARRAY CALLS THERE CAN BE MANY
			//if ($row->xlat === $lastKey){
			//	continue;
			//}
			//$lastKey = $row->xlat;
			$lines[] = self::getLine($row->xlat,$row->text);
		}

		// todo ? Eliminate dupes (same xlat) Should be done at input phase. It is extract that gives us dupes?

		// Lines are in alpha order, good.
		// Dump::say($lines);
		// Dump::say($Q->createCommand()->getRawSql()); // SELECT * FROM `hank_lang` WHERE `iso`='en' ORDER BY `xlat`

		return $lines;
	}

	// Output single line of json. Key in single quotes. Value in double quotes
	public static function getLine($xlat, $text){
		$SQ = "'";
		$DQ = '"';
		$key = $xlat;
		$val = $DQ . str_replace( $DQ, $SQ, $text ) . $DQ;
		$key = $SQ . str_replace( $SQ, "\\'", $key) . $SQ;
		return $key . ':' . $val;    // 'my phrase' :  "mon phrase"
	}

	/*---------------------------------------------------------------------------------------------------------------*/
	// EXTRACT language updates from the latest release : find new $t() and ones no longer used
	// Update DB tables

	public function extract(){
		$this->ready('extract');

		// Remove prior output
		$fnOutput = Yii::$app->params['BHSITE_PATH'] .'output.json';
		$fnBackup = Yii::$app->params['BHSITE_PATH'] .'output.' . date('YmdHis') . 'json';

		// Make a backup
		// file_put_contents($fnBackup, file_get_contents($fnOutput));

		/* Why? What was I thinking?
		$this->log->put("Removing $fnOutput");
		if ( is_readable($fnOutput)){
			unlink($fnOutput);
			touch($fnOutput);
		}
		*/

		// Change to BHSITE folder and run vue-i18n-extract
		$this->log->put("Execute npm run vue-i18n-extract");
		$cmdBuff=[];$rc=0;
		$cmd='npm run vue-i18n-extract';
		$path = Yii::$app->params['BHSITE_PATH'];
		$cwd = getcwd();
		try{
			chdir($path);
			exec($cmd . ' 2>&1',$cmdBuff,$rc);
			//Dump::say($cmdBuff, 'RC', $rc);
		} catch(\Exception $e){
			$this->log->put($e->getMessage());
			$this->log->put($e->getTraceAsString());
			$this->log->put("Fail to complete");
			$this->addError('Exception logged');
		} finally {
			chdir($cwd);
		}
		// Copy output to log
		foreach($cmdBuff AS $line){
			$this->log->put($line);
		}
		if ($rc !== 0){
			$this->log->put("npm run vue-i18n-extract FAILED");
			return false;
		}

		$fnOutput = Yii::$app->params['BHSITE_PATH'] .'output.json';
		if (! is_readable($fnOutput)){;
			$this->log->put('Seems extract produced nothing?  ' . $fnOutput);
			return false;
		}
		if ( filesize($fnOutput) < 1){
			$this->log->put('Nothing to do. Zero bytes in ' . $fnOutput);
			return false;
		}
		$obj = json_decode(file_get_contents($fnOutput));
		/* This is what vue-extract gives us.
			OBJ 1
			stdClass#1
			(
			    [missingKeys] => array
			    (
			        [0] => stdClass#2
			        (
			            [path] => done
			            [file] => ./src/components/account/BuyTokens.vue
			            [line] => 17
			            [language] => index
			        )
			        [1] => stdClass#3
			        (
			            [path] => Email receipt
			            [file] => ./src/components/account/BuyTokens.vue
			            [line] => 42
			            [language] => index
			        )
			        [2] => stdClass#4
			        (
			            [path] => Change
			            [file] => ./src/components/account/BuyTokens.vue
			            [line] => 119
			            [language] => index
			        )
					Dump::say('OBJ 1', $obj);
		*/

		// All we need are the phrases, without all the duplicate entries for each lang
		// I wonder what their plan was, and why they needed line numbers for every occurrence.
		$obj = $this->_eliminate_duplicates($obj);
		/*
				OBJ 2
				stdClass#1
				(
				    [missingKeys] => array
				    (
				        [0] => ADD ME Never darken my door again
				        [1] => ALL
				        [2] => API TEST
				        [3] => About
				        [4] => About Us
				        [5] => Access
				        [6] => Account
				        [7] => Action
				        [8] => Activity Feed
			Dump::say('OBJ 2', $obj);
		*/

		if ( count($obj->missingKeys) === 0 AND count($obj->unusedKeys) === 0 AND count($obj->maybeDynamicKeys) === 0){
			$this->log->put('Nothing to do. No entries in ' . $fnOutput);
			return false;
		}

		$this->_missingKeys($obj->missingKeys);
		$this->_unusedKeys($obj->unusedKeys);
		$this->_maybeDynamicKeys($obj->maybeDynamicKeys);

		// Now generate new language files from DB table
		//$langTable = new SiteLang();
		//return $langTable->generate();
		return true;
	}

	private function _missingKeys($list){

		$this->log->put("Adding missing keys..."); // todo HANGS AFTER THIS MESSAGE. WHY?

		// For every missing phrase
		foreach ($list AS $phrase){

			$this->log->put("EN phrase $phrase ");

			// Assume new record
			$bUpdate = false;
			$bActivate = false;


			// Do we find an english master xlat key?
			// NB This must be is a case-sensitive search
			if (!$row = HankLang::find()->where(['iso'=>'en'])->andWhere(['= BINARY', 'xlat', $phrase])->one()) {

				// Not found. Add the english
				$en = $this->_addEnglish($phrase);

			} else{
				// Found
				$en = $row;

				// Has the text changed?
				if ($en->text != $phrase){

					// Update the english master with changed text
					$this->_updateEnglish($phrase,$en);

					$bUpdate = true;    // all other languages must translate the update

				} else {
					// Found same key and text. Activate this row for output phase
					if ( ! $en->active){
						$bActivate = true;
						$en->active=true;
						$en->save(false);
						$this->log->put("ACTIVATE en {$phrase}");
					}
				}

			}

			// We now have the english record. Activate and add other languages for this phrase
			foreach (HankLang::LANGS AS $iso){
				if ($iso=='en'){
					continue;
				}
				$this->log->put("OTHERS $iso phrase $phrase ");

				if (!$row = HankLang::find()->where(['iso'=>$iso])->andWhere(['= BINARY', 'xlat', $phrase])->one()) {

					// We do not have a translation for this phrase. Get it from google now.
					if ( ! ($text=SiteLang::google($phrase,$iso)) ) {
						$this->log->put("GOOGLE XLAT FAIL FOR PHRASE" . $phrase . ' ISO=' . $iso);
						$this->addError("GOOGLE XLAT FAIL FOR PHRASE" . $phrase . ' ISO=' . $iso);
						return false;
					}

					$en->isNewRecord = true;
					$en->lang_id = null;
					$en->text=$text;
					$en->source='google';
					$en->iso = $iso;
					$en->active = true;
					$en->save(false);
					$this->log->put("ADD GOOGLED $iso ACTIVE " . $en->xlat . '=>' . $en->text);

				} else {

					if ($bUpdate){
						if ($row->locked){
							// Human has locked the translation, do not use google again
							$this->log->put("LOCKED. $iso ACTIVATE " . $row->xlat .  '=>' . $row->text);
						} else{
							// The english text for an existing xlat key has changed
							if ( ! ($text=SiteLang::google($phrase,$iso)) ) {
								$this->log->put("GOOGLE XLAT FAIL FOR PHRASE" . $phrase . ' ISO=' . $iso);
								$this->addError("GOOGLE XLAT FAIL FOR PHRASE" . $phrase . ' ISO=' . $iso);
								return false;
							}
						}
					} else {
						$text = $row->text;
					}

					if (  (! $row->active) OR ($text !== $row->text) OR ($bActivate) ){
						$row->text = $text;
						$row->active  = true;
						$row->save(false);
						$this->log->put("FOUND. $iso ACTIVATE " . $row->xlat .  '=>' . $row->text);
					}
				}
			}
		}

	}

	/**
	 * @param $phrase
	 *
	 * @return HankLang
	 */
	private function _addEnglish($phrase){
		$flds = [
			'iso'=>'en',
			'source'=>'extract',
			'active'=>true,
			'xlat'=>$phrase,
			'text'=>$phrase,
		];
		$en = HankLang::add($flds);
		$this->log->put("ADD en {$phrase}");
		return $en;
	}

	// xlat key is same but text has changed
	private function _updateEnglish($phrase, $en){
		$en->text = $phrase;
		$en->active = true;
		$en->save(false);
		$this->log->put("UPDATE en $phrase");
		return $en;
	}

	private function _updateOther(){

	}
	private function _addOther(){

	}

	// Mark existing translation as not asctive (generation will skip it)
	private function _unusedKeys($list){
		$this->log->put('Marking un-used keys as not active...');
		$count = 0;
		foreach ($list AS $phrase){
			$count++;
			if (PHP_SAPI === 'cli') { echo "$phrase\r";}    // Look alive. We are not hung. A large files may take time.
			HankLang::updateAll(['active'=>0], ['xlat'=>$phrase]);
		}
		$this->log->put("Marked $count rows unusedKeys as inactive...");
		// todo Later, remove inactive rows with tm_update a year old or something like that
	}

	private function _maybeDynamicKeys($obj){
		$this->log->put("*** _maybeDynamicKeys HUH WUT?..."); // unsure. Have not seen this yet.
	}

	// vue-i18n-extract produces one line for every phrase found. The same phrase may appear many times in the source.
	// Convert each array into a simple list of phrases, all unique
	private function _eliminate_duplicates($obj) {
		$filteredObj = [];
		foreach(['missingKeys','unusedKeys', 'maybeDynamicKeys'] AS $group){
			$filteredObj[$group] = [];
			foreach($obj->{$group} AS $phrase){
				if (! in_array($phrase->path, $filteredObj[$group]) ){
					$filteredObj[$group][]=$phrase->path;
				}
			}
			sort($filteredObj[$group]);
		}
		$this->log->put('MISSING KEYS WITHOUT DUPLICATES:');
		$this->log->put( Dump::log($filteredObj));
		return json_decode(json_encode($filteredObj));
	}

	/*---------------------------------------------------------------------------------------------------------------*/
	// STATIC HELPERS

	/**
	 * @param $txt
	 * @param $iso
	 * @return mixed
	 *
	 *      Google api translate one phrase.  text,iso --> translated text
	 */
	public static function google($txt,$iso)
	{
		try{
			$apiKey = \Yii::$app->params['GOOGLE-XLAT'];
			$url = 'https://www.googleapis.com/language/translate/v2?key=' . $apiKey . '&q=' . rawurlencode($txt) . '&source=en&target=' . $iso;
			$handle = curl_init($url);
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($handle);
			//Dump::say('RESPONSE', $response);
			$responseDecoded = json_decode($response, true);
			//Dump::say('RESPONSE DECODED', $response);
			curl_close($handle);
			// Dump::say('RESPONSE',$response);
			return  $responseDecoded['data']['translations'][0]['translatedText'];
		}catch(\Exception $e){
			\stewlog::except($e->getMessage(),$e->getTraceAsString());
		}
		return false;
	}

	// dsm 28 sep 24 : We decide to deprecate module prefixes in language masters.
	// We don't load strings by module, so what is the point?
	// Get (sorted asc) array of distinct module names in language table
	//			array
	//			(
	//			[0] => null
	//			[1] => blog
	//			[2] => cart
	//			[3] => cats
	//			[4] => chat
	//			[5] => comment
	//			[6] => core
	//			[7] => faq
	//			[8] => loc
	//			[9] => mail
	//			[10] => opt
	//			[11] => photo
	//			[12] => pofile
	//			[13] => profile
	//			[14] => report
	//			[15] => search
	//			[16] => status
	//			[17] => video
	//			[18] => videos
	//			[19] => wm
	//			)
	public static function  getModules(){
		static $cache = NULL;
		if ($cache){
			return $cache;
		}
		$modules = HankLang::getDb()->createCommand('SELECT DISTINCT module from hank_lang ORDER BY module')->queryAll();
		$cache = [];
		foreach ($modules AS $m){
			$cache[]=$m['module'];
		}
		return $cache;
	}

	// Text must be either a key (xlat_my_phrase_key) or english. todo is this used any more?
	public static function isKey($v){
		return substr($v,0,5)==='xlat_';
	}

	// File names required by i18n include dialect
	public static function fullISO($iso){
		if ($iso==='en'){
			return 'en-us';         // Master language
		}
		return $iso . '-' . $iso;   // But mostly we don't care.
	}


}


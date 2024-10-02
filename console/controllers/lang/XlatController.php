<?php

// dsm 27 sep 24 : Utilities, import, tests

namespace console\controllers\lang;

use common\modules\api3\models\SiteLang;
use common\modules\fox\models\HankLang;
use Yii;
use yii\console\Controller;
use common\components\Dump;


class XlatController extends Controller
{
	const ROOT = '/home/me/bhjs/BHSITE/i18n/';

	// Supported languages
	public $langs;
	public $out;
	public $master;

	public function init(){
		$this->langs = HankLang::LANGS;
		$this->out = $this->master = HankLang::LANG_ARRAY;
	}

	/*---------------------------------------------------------------------------------------------------------------*/
	// Empty the language master files and set all DB rows to inactive.
	// The next time we generate should be a clean run. Add all and only what is in the cade.
	// This is a dev tool and should not be required in production?
	public function actionEmpty(){
		$y = readline('YOU SURE? Empty master files and mark all table rows as inactive?');
		if ( strtolower($y) !== 'y'){
			return 0;
		}
		$count=0;
		foreach( HankLang::LANGS AS $iso){
			$path = self::ROOT .  'lang/' . SiteLang::fullISO($iso);
			if (! is_dir($path)){
				$rc = @mkdir($path);
				echo ($rc?'PASS':'FAIL') . 'mkdir ' . $path . PHP_EOL;
			}
			$fnMaster = self::ROOT .  'lang/' . SiteLang::fullISO($iso) . '/index.js';
			file_put_contents($fnMaster, "module.exports = {};\n");
			if (is_readable($fnMaster) AND filesize($fnMaster) === 21 ){
				echo 'Created empty ' . $fnMaster . PHP_EOL;
			} else{
				echo 'Missing or unexpected size for ' . $fnMaster . PHP_EOL;
			}
			$count++;
		}
		$n = HankLang::getDb()->createCommand('UPDATE `hank_lang` SET active=false')->execute();
		echo "$count files emptied\n";
		echo "$n rows set inactive\n";
		return 0;
	}
	/*---------------------------------------------------------------------------------------------------------------*/


	/*---------------------------------------------------------------------------------------------------------------*/
	// RUN ONCE. LOAD ALL PILOT STRINGS INTO HankLang
	// 1. Copy #/Users/me/home/me/bhjs/BHCHAT20/shared/localization/master to ~/BHCHATXLAT
	// 2. Empty HankLang
	// 3. bh i18n/xlat/pilot
	public function actionPilot(){
		//die('THIS IS DONE I THINK ?'); Nope. Still Work In Progress
		foreach(['en','es','de','fr','it'] AS $iso){
			$path= '/home/me/BHCHATXLAT/master/' . $iso . '.json';
			$this->_pilot2site($path,$iso);
		}
		//$path='/home/me/sites/www/test.json'; // BHCHAT20 MASTER
		//$a = $this->_pilot2site($path,'en');
		return 0;
	}
	private function _pilot2site($path,$iso){
		echo "MASTER $iso\n";
		$in = file_get_contents($path);
		$master = json_decode($in, true);
		$res=[];
		foreach($master AS $k=>$v){
			if ( is_array($v) ){
				foreach($v AS $k2=>$v2){
					// K = K profile.xlat_last_login
					//echo 'K ' . $k  . '.' . $k2 . ' . V ' . $v2 . PHP_EOL;
					//$res = $k  . '.' . $k2 . '.'  . $v2;
					//$this->_add($iso, $k, $k2, $v2);
					// Strip module
					$i = strpos($k, '.');
					$key = substr($k, $i, 512);
					$line = SiteLang::getLine($k2, $v2);
					echo $line . PHP_EOL;
				}
				continue;
			}
			// Never happens using BHCHAT20 masters
			//die('huh wut?');
			//echo 'K ' . $k . ' . V ' . $v . PHP_EOL;
			//$res = $k  . '.' .  $v2;
		}
		//return $res;
	}
	private function _add($iso,$module,$xlat,$text){
		return;
		$flds=[
			'iso'=>$iso,
			'module'=>$module,
			'xlat'=>$xlat,
			'text'=>$text,
		];
		$model = HankLang::add($flds);
		if ($model->hasErrors()){
			Dump::say($model->getErrors());
			die('FAIL');
		}
	}
	/*---------------------------------------------------------------------------------------------------------------*/

	// Test write to steward_lang at live server. There is no test DB
	// bh lang/xlat/db
	public function actionDb() {
		$flds = [
			'iso'    => 'en',
			'xlat'   => 'test',
			'text'   => 'test',
		];
		$r    = HankLang::add( $flds );
		Dump::say( 'ADD', $r->getAttributes() );
		if ( $r->hasErrors() ) {
			Dump::say($r->getErrors());
		}
	}

	// bh lang/xlat/hello
	public function actionHello(){
		echo "Hello world\n";
	}

	/*---------------------------------------------------------------------------------------------------------------*/
	// Ok. We  are ready to release.
	// bh lang/xlat/extract
	public function actionExtract(){

		$y = readline('Have you pulled latest BHSITE to this server (Y/y) ?');
		if ( strtolower($y) !== 'y'){
			return 0;
		}
		$siteLang = new SiteLang();
		$rc = 0;
		try {
			$rc = $siteLang->extract();
		} catch ( \Exception $e) {
			echo $e->getMessage() . PHP_EOL;
			echo $e->getTraceAsString() . PHP_EOL;
		} finally {
			if ($siteLang->log){
				//$siteLang->log->put($siteLang->rpt->report());
				echo 'cat ' . $siteLang->log->fnLog . PHP_EOL;
			}
		}
		return $rc;
	}


	/*---------------------------------------------------------------------------------------------------------------*/
	//
	// bh lang/xlat/generate
	public function actionGenerate() {
		$siteLang = new SiteLang();
		return $siteLang->generate();
	}


	/*---------------------------------------------------------------------------------------------------------------*/
	// Soup to nuts completely re-generate language masters
	//
	// bh lang/xlat/release
	public function actionRelease() {

		if ( $this->actionEmpty() ) {
			echo ( 'FAIL EMPTY' );
		} else {

			// run vue-i18n-extract and update hank_lang)
			if (! $this->actionExtract() ) {
				echo ( 'FAIL EXTRACT' );
			}
			else {
				if ( ! $this->actionGenerate() ) {
					echo ('FAIL GENERATE');
				} else {
					echo ('*** COMPLETED NORMALLY');
				}
			}
			//if ($this->rpt){
			//	$this->log->put($this->rpt->report());
			//`}
		}
	}

	// UPDATE ONLY WHAT CHANGED (Process missing keys and deleted keys)
	public function actionUpdate() {
		// run vue-i18n-extract and update hank_lang)
		if (! $this->actionExtract() ) {
			echo ( "FAIL EXTRACT\n" );
		}
		else {
			if ( ! $this->actionGenerate() ) {
				echo ("FAIL GENERATE\n");
			} else {
				echo ("*** COMPLETED NORMALLY\n");
			}
		}
	}

	// It may not be necessary to empty the masters first: extract says it has removed un-used entries.
	// So all we need to do is add missing.


	/*---------------------------------------------------------------------------------------------------------------*/
	//
	// bh lang/xlat/pretty
	public function actionPretty() {
		$fnOutput = Yii::$app->params['BHSITE_PATH'] .'output.json';
		$obj = json_decode(file_get_contents($fnOutput));

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

		//Dump::say( json_encode($filteredObj));
		$fnPretty = Yii::$app->params['BHSITE_PATH'] .'pretty.json';
		file_put_contents( $fnPretty, json_encode($obj, JSON_PRETTY_PRINT) );
	}

	public function actionUp(){
		$key='All';
		$n = HankLang::updateAll(['active'=>1], ['xlat'=>$key]);
		Dump::say('n',$n);
	}
	public function actionFind() {
		$key='Vendor info';
		echo '========== KEY ' . $key;
		if ($row = HankLang::find()->where(['iso'=>'en'])->andWhere(['= BINARY', 'xlat', $key])->one() ){
			Dump::say('FOUND' ,$row->getAttributes());
		} else {
			echo "KEY $key not found\n";
		}

		$key='Vendor Info';
		echo '========== KEY ' . $key;
		if ($row = HankLang::find()->where(['iso'=>'en'])->andWhere(['= BINARY', 'xlat', $key])->one() ){
			Dump::say('FOUND' ,$row->getAttributes());
		} else {
			echo "KEY $key not found\n";
		}

	}

	// Problem. Why does english have more keys now?
	/*
	 *  798 written to /home/me/bhjs/BHSITE//i18n/lang/en-us/index.js
		725 written to /home/me/bhjs/BHSITE//i18n/lang/es-es/index.js
		725 written to /home/me/bhjs/BHSITE//i18n/lang/de-de/index.js
		725 written to /home/me/bhjs/BHSITE//i18n/lang/fr-fr/index.js
		725 written to /home/me/bhjs/BHSITE//i18n/lang/it-it/index.js
		725 written to /home/me/bhjs/BHSITE//i18n/lang/pt-pt/index.js
		725 written to /home/me/bhjs/BHSITE//i18n/lang/ru-ru/index.js
	 */
	public function actionCmp(){
		$en=explode(PHP_EOL, file_get_contents('/home/me/bhjs/BHSITE/i18n/lang/en-us/index.js'));
		$fr=explode(PHP_EOL, file_get_contents('/home/me/bhjs/BHSITE/i18n/lang/fr-fr/index.js'));

		for($i=0; $i< count($en); $i++){
			if ( ! str_starts_with(trim($en[$i]),"'")){
				continue;
			}

			echo 'EN ',  $i, ' ', $EN=$this->_key($en[$i]) . PHP_EOL;
			echo 'FR ',  $i, ' ', $FR=$this->_key($fr[$i]) . PHP_EOL;
			echo PHP_EOL;

			if ($EN!=$FR){
				return 1;
			}
		}

	}
	private function _key($s){
		$i=strpos($s, "'");
		$j=strpos($s, "'", $i+1);
		return substr($s, $i+1, $j-$i-1);
	}
}

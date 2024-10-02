<?php

namespace common\modules\fox\models;

use Yii;

/**
 * BACKEND GEN CRUD VUE MODEL This is the model class for table "hank_lang".
 *
 * @property integer $lang_id
 * @property string $iso
 * @property string $module
 * @property string $xlat
 * @property string $text
 * @property integer $user_id
 * @property integer $active
 * @property integer $locked
 * @property string $source
 * @property integer $tm_update
 */
class HankLang extends \yii\db\ActiveRecord
{
	const LANGS = ['en','es','de','fr','it','pt','ru'];
	const LANG_ARRAY = ['en'=>[],'es'=>[],'de'=>[],'fr'=>[],'it'=>[],'pt'=>[],'ru'=>[]];

	public $add_all_langs;
	public $xlat_all_langs;

	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return 'hank_lang';
	}
	public static function getDb()
	{
		return \Yii::$app->dbLANG; // ******* ATTENTION! NO SEPARATE TEST DB. ALWAYS AND ONLY MASTER ON LIVE DB SERVER
		// If you are developing on the test box, keep good backups. Hmm. What we need to do is create a test lang table. D'OH!
	}


	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['iso','text'], 'required'],
			[['source'], 'string'],

			// Must have non-blank xlat key and text value or Google Translate will puke. No max len.
			['xlat', 'string', 'length'=>[1]],
			['text', 'string', 'length'=>[1]],


			[['iso'], 'string', 'max' => 2],
			[['module'], 'string', 'max' => 255],   // todo no longer used
			[['active'],'boolean'],
			[['locked'],'boolean'],
			[['user_id'], 'integer'],
			[['tm_update'], 'integer'],

			// todo remove now. created SiteLang model instead
			[['add_all_langs','xlat_all_langs','source'], 'safe']
		];
	}

	public function validate( $attributeNames = null, $clearErrors = true ) {
		if ( ! parent::validate( $attributeNames, $clearErrors ) ){
			return false;
		}

		// Make sure we know this language
		if (! in_array($this->iso, self::LANGS) ){
			$this->addError('iso','ISO must one of ]' . implode(',',self::LANGS) . ']');
		}

		// We can only add for all langs or lookup if the iso is en
		if ( ($this->xlat_all_langs OR $this->add_all_langs) AND $this->iso !='en' ){
			$this->addError('iso','Can only translate or add all for iso=en');
		}

		// todo: Check for duplicate

		return !$this->hasErrors();
	}

	// THE PLAN: At some point, clear all old rows
	public function beforeSave( $insert ) {
		if ( ! parent::beforeSave( $insert ) ) {
			return false;
		}
		$this->tm_update = time();
		return true;
	}


	public static function add($flds){
		$model = new HankLang();
		if ( ! isset($flds['active']) ){
			$flds['active'] = true;// Assume anything newly added is intended to be active.
		}
		if ( ! isset($flds['user_id']) ){
			$flds['user_id'] = 1; // Default to admin todo: HANK_LANG_MAN?
		}
		$model->load($flds,'') AND $model->save();
		return $model;
	}

	/**
	 * @inheritdoc
	 * @return \common\modules\fox\models\query\HankLangQuery the active query used by this AR class.
	 */
	public static function find()
	{
		return new \common\modules\fox\models\query\HankLangQuery(get_called_class());
	}

	public function fields() {
		switch ( $this->scenario ) {

			case 'user':
				return [
					'table'  => [ 'filters' => [], 'actions' => [], 'readOnly' => true ],
					'fields' => $this->toVue( array_keys( $this->attributes ) )
					//'fields' => $this->toVue( [ 'vid', 'description', 'tm_cron', 'status', 'step' ] )
				];

			case 'admin':
				return [
					'table'  => [ 'filters' => [ '*' ], 'actions' => [ '*' ], 'readOnly' => false ],
					'fields' => $this->toVue( array_keys( $this->attributes ) )
				];
			default:
				return [];
		}
	}
	// Given array of field names, extract vue definitions
	public function toVue($flds){
		$result=[];
		$vueDefs = $this->vueDefs();
		foreach($vueDefs AS $def){
			if ( in_array( $def['name'], $flds)) {
				$result[] = $def;
			}
		}
		return $result;
	}


	public function vueDefs()
	{
		return [
			'lang_id'=>[
				'name'=>'lang_id',
				'type'=>'integer',
				'label'=>'id',
				'isEditable'=>false,
				//'validate'=>'string',
				'sortable'=>true,				// enable grid header sort by this column',
				'filter'=>true				// enable grid header filter for this column',
			],
			'iso'=>[
				'name'=>'iso',
				'type'=>'string',
				'label'=>'iso',
				'isEditable'=>true,
				//'validate'=>'string',
				'sortable'=>true,				// enable grid header sort by this column',
				'filter'=>true				// enable grid header filter for this column',
			],
			'user_id'=>[
				'name'=>'user_id',
				'type'=>'integer',
				'label'=>'user',
				'isEditable'=>false,
				'sortable'=>true,
				'filter'=>true
			],
			'module'=>[
				'name'=>'module',
				'type'=>'string',
				'label'=>'2bDeprecated',
				'isEditable'=>true,
				//'validate'=>'string',
				'sortable'=>true,				// enable grid header sort by this column',
				'filter'=>true				// enable grid header filter for this column',
			],
			'active'=>[
				'name'=>'active',
				'type'=>'boolean',
				'label'=>'active',
				'isEditable'=>false,
				'sortable'=>true,
				'filter'=>true
			],
			'locked'=>[
				'name'=>'locked',
				'type'=>'boolean',
				'label'=>'locked',
				'isEditable'=>true,
				'sortable'=>true,
				'filter'=>true
			],
			'source'=>[
				'name'=>'source',
				'type'=>'select',
				'label'=>'source',
				'isEditable'=>false,
				'sortable'=>true,
				'filter'=>true,
				'options'=>[
					['label'=>'undef','value'=>'undef'],
					['label'=>'google','value'=>'google'],
					['label'=>'user','value'=>'user'],
					['label'=>'pilot','value'=>'pilot'],
				],
			],
			'xlat'=>[
				'name'=>'xlat',
				'type'=>'string',
				'label'=>'xlat',
				'isEditable'=>true,
				//'validate'=>'string',
				'sortable'=>true,				// enable grid header sort by this column',
				'filter'=>true				// enable grid header filter for this column',
			],
			'text'=>[
				'name'=>'text',
				'type'=>'textarea',
				'label'=>'text',
				'isEditable'=>true,
				//'validate'=>'string',
				'sortable'=>true,				// enable grid header sort by this column',
				'filter'=>true				// enable grid header filter for this column',
			],
		];
	}
	/*
ALTER TABLE `hank_lang` CHANGE `googled` `source` ENUM('undef','google','user','pilot') NULL DEFAULT NULL;
ALTER TABLE `hank_lang` CHANGE `source` `source` ENUM('extract','google','user','pilot') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
	*/


}

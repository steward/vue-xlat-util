# vue-xlat-util
PHP utilities for coping with vue-i18n-extract


# General Strategy

Maintain table hank_lang with keys and their translations, one row per iso.

The purpose is simply to reduce calls to google translate. If we have translated a phrase before, no need to do it again.

At some point, delete all rows from hank_lang where active=false and tm_update is greater than some timestamp.

Otherwise the table retains every phrase ever used since the day you started.

Only rows with active=true are in use after any given update.

We use the same table for development and production. Therefore it is in a separate database.

## MBH
Revert to english only.
Do all translations via vue/extract.


## Add New Language

5 steps:

### 1. Yii

Update common/modules/fox/models/HankLang.php

Add the iso code for the new language

```php
	// ESSENTIAL THAT EN BE THE FIRST LANG
	const LANGS = ['en','es','de','fr','it','pt','ru'];
	const LANG_ARRAY = ['en'=>[],'es'=>[],'de'=>[],'fr'=>[],'it'=>[],'pt'=>[],'ru'=>[]];
```


Update common/models/account/User.php

[['language_id'], 'in', 'range'=> ['en','fr', 'de', 'es', 'it', ...] ],

_WIP search for "'en','es'" to find more places._

_todo: globalize in HankShareConfig?_

_todo: no,  try to use HankLang.php. Maybe can include in shared?_

### 2. MBH

Do nothing? Remove language pack for PT?


### 3. Vue src

Create empty  i18m/xx-xx/index.js

_No. This is automated now. But maybe still required depending on workflow and git._

### 4. Add fields to kvs_category file dir_xx

At one time the system was compatible with KVS. We still use their video category table schema

---

## vue-i18n-extract

https://github.com/Spittal/vue-i18n-extract

---

## The plan

Add/Use $t() everywhere with EN keys.

At release time,  run utility...

1. run vue-extract
2. for each removed, mark in DB hank_lang as inactive.
3. for each new, lookup in hank_lang and mark as active.
4. if new not found, use google translate api and add for all langs
5. Regenerate master lang files for BHSITE

DATABASE steward_lang
TABLE hasnk_lang
SERVER: DEX231
EG: THERE IS ONLY ONE LANGUAGE DB TABLE! NO TEST DB.

Deprecate phpfox2_lang_phrase as much as possible. Maybe replace it with hank_lang if possible.
Probably not. Unsure.

# YII SCRIPTS
## HankLang

Model for all phrases. Generate master files from this table.
DATABASE: steward_lang. THERE IS NO DEV TABLE. ALL IS LIVE.

## SiteLang

Extends HankLang

## lang/xlat/extract

Console task to run run vue-i18n utility and update the langugae table and generate new master language files

## xlatHelper

Probably should move to SiteLang

# VUE SCRIPTS

## admin/LangTable

Grid. Maybe create user version for translators at some point.
Don't need this for admin if extract processe works, but it is helpful to see what is accumulating...

---

# The Final Frontier!

1. Run bh lang/xlat/empty
2. Run bh lang/xlat/extract

Consult source in console/controllers/lang/XlatController.php

Running empty before extract _should_ be optional. It was helpful at some point for development.

It simply clears all master language json files in vue, and marks all translations aas inactive.


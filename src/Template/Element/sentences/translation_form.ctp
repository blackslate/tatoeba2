<?php
$langs = $this->Languages->profileLanguagesArray(false, false);
$userLanguagesJSON = htmlspecialchars(json_encode($langs), ENT_QUOTES, 'UTF-8');
?>
<div ng-if="vm.isTranslationFormVisible" style="background: #fafafa; padding-top: 10px; border-top: 1px solid #f1f1f1"
     ng-init="vm.initUserLanguages(<?= $userLanguagesJSON ?>)">
    <form layout="column" layout-margin>
        <md-input-container>
            <label><?= __('Translation') ?></label>
            <textarea id="translation-form-<?= $sentenceId ?>" ng-model="vm.translationText"></textarea>
        </md-input-container>
        
        <div layout="row" layout-align="start center">
            <md-input-container flex="50">
                <label><?= __('Language') ?></label>
                <md-select ng-model="vm.translationLang">
                    <md-option ng-value="auto"><?= __('Auto detect') ?></md-option>
                    <md-option ng-repeat="(code, name) in vm.userLanguages" ng-value="code">
                        {{name}}
                    </md-option>
                </md-select>
            </md-input-container>
            
            <div style="padding: 10px 10px 0 10px">
                <img ng-src="/img/flags/{{vm.translationLang}}.svg" ng-if="vm.translationLang" width="30" height="20"/>
            </div>
        </div>

        <div layout="row" layout-align="end center">
            <md-button class="md-raised" ng-click="vm.isTranslationFormVisible = false">
                <?= __('Cancel') ?>
            </md-button>
            <md-button class="md-raised md-primary">
                <?= __('Submit translation') ?>
            </md-button>
        </div>
    </form>
</div>
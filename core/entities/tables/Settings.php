<?php

    namespace thebuggenie\core\entities\tables;

    use thebuggenie\core\framework,
        b2db\Core,
        b2db\Criteria,
        b2db\Criterion;

    /**
     * Settings table
     *
     * @author Daniel Andre Eikeland <zegenie@zegeniestudios.net>
     * @version 3.1
     * @license http://opensource.org/licenses/MPL-2.0 Mozilla Public License 2.0 (MPL 2.0)
     * @package thebuggenie
     * @subpackage tables
     */

    /**
     * Settings table
     *
     * @package thebuggenie
     * @subpackage tables
     *
     * @Table(name="settings")
     */
    class Settings extends ScopedTable
    {

        const B2DB_TABLE_VERSION = 2;
        const B2DBNAME = 'settings';
        const ID = 'settings.id';
        const SCOPE = 'settings.scope';
        const NAME = 'settings.name';
        const MODULE = 'settings.module';
        const VALUE = 'settings.value';
        const UPDATED_AT = 'settings.updated_at';
        const UID = 'settings.uid';

        public function _setupIndexes()
        {
            $this->_addIndex('scope_uid', array(self::SCOPE, self::UID));
        }

        protected function _initialize()
        {
            parent::_setup(self::B2DBNAME, self::ID);
            parent::_addVarchar(self::NAME, 45);
            parent::_addVarchar(self::MODULE, 45);
            parent::_addVarchar(self::VALUE, 200);
            parent::_addInteger(self::UID, 10);
            parent::_addInteger(self::UPDATED_AT, 10);
        }

        public function getSettingsForScope($scope, $uid = 0)
        {
            $crit = $this->getCriteria();
            if (framework\Context::isUpgrademode())
            {
                $crit->addSelectionColumn(self::NAME);
                $crit->addSelectionColumn(self::MODULE);
                $crit->addSelectionColumn(self::VALUE);
                $crit->addSelectionColumn(self::UID);
                $crit->addSelectionColumn(self::SCOPE);
            }
            $ctn = $crit->returnCriterion(self::SCOPE, $scope);
            $ctn->addOr(self::SCOPE, 0);
            $crit->addWhere($ctn);
            $crit->addWhere(self::UID, $uid);
            $res = $this->doSelect($crit, 'none');
            return $res;
        }

        public function saveSetting($name, $module, $value, $uid, $scope)
        {
            $crit = $this->getCriteria();
            $crit->addWhere(self::NAME, $name);
            $crit->addWhere(self::MODULE, $module);
            $crit->addWhere(self::UID, $uid);
            $crit->addWhere(self::SCOPE, $scope);
            $res = $this->doSelectOne($crit);

            if ($res instanceof \b2db\Row)
            {
                $theID = $res->get(self::ID);
                $crit2 = new Criteria();
                $crit2->addWhere(self::NAME, $name);
                $crit2->addWhere(self::MODULE, $module);
                $crit2->addWhere(self::UID, $uid);
                $crit2->addWhere(self::SCOPE, $scope);
                $crit2->addWhere(self::ID, $theID, Criteria::DB_NOT_EQUALS);
                $res2 = $this->doDelete($crit2);

                $crit = $this->getCriteria();
                $crit->addUpdate(self::NAME, $name);
                $crit->addUpdate(self::MODULE, $module);
                $crit->addUpdate(self::UID, $uid);
                $crit->addUpdate(self::VALUE, $value);
                $crit->addUpdate(self::UPDATED_AT, time());
                $this->doUpdateById($crit, $theID);
            }
            else
            {
                $crit = $this->getCriteria();
                $crit->addInsert(self::NAME, $name);
                $crit->addInsert(self::MODULE, $module);
                $crit->addInsert(self::VALUE, $value);
                $crit->addInsert(self::SCOPE, $scope);
                $crit->addInsert(self::UID, $uid);
                $crit->addInsert(self::UPDATED_AT, time());
                $this->doInsert($crit);
            }
        }

        public function deleteModuleSettings($module_name, $scope = null)
        {
            $scope = ($scope === null) ? framework\Context::getScope()->getID() : $scope;
            $crit = $this->getCriteria();
            $crit->addWhere(self::MODULE, $module_name);
            $crit->addWhere(self::SCOPE, $scope);
            $this->doDelete($crit);
        }

        public function deleteAllUserModuleSettings($module_name, $scope = null)
        {
            $crit = $this->getCriteria();
            $crit->addWhere(self::MODULE, $module_name);
            $crit->addWhere(self::UID, 0, Criteria::DB_GREATER_THAN);
            if ($scope !== null)
            {
                $crit->addWhere(self::SCOPE, $scope);
            }
            $this->doDelete($crit);
        }

        public function loadFixtures(\thebuggenie\core\entities\Scope $scope)
        {
            $i18n = framework\Context::getI18n();

            $settings = array();
            $settings[\thebuggenie\core\framework\Settings::SETTING_THEME_NAME] = 'oxygen';
            $settings[\thebuggenie\core\framework\Settings::SETTING_REQUIRE_LOGIN] = false;
            $settings[\thebuggenie\core\framework\Settings::SETTING_DEFAULT_USER_IS_GUEST] = true;
            $settings[\thebuggenie\core\framework\Settings::SETTING_ALLOW_REGISTRATION] = true;
            $settings[\thebuggenie\core\framework\Settings::SETTING_RETURN_FROM_LOGIN] = 'referer';
            $settings[\thebuggenie\core\framework\Settings::SETTING_RETURN_FROM_LOGOUT] = 'home';
            $settings[\thebuggenie\core\framework\Settings::SETTING_SHOW_PROJECTS_OVERVIEW] = true;
            $settings[\thebuggenie\core\framework\Settings::SETTING_ALLOW_USER_THEMES] = false;
            $settings[\thebuggenie\core\framework\Settings::SETTING_ENABLE_UPLOADS] = false;
            $settings[\thebuggenie\core\framework\Settings::SETTING_ENABLE_GRAVATARS] = true;
            $settings[\thebuggenie\core\framework\Settings::SETTING_UPLOAD_RESTRICTION_MODE] = 'blacklist';
            $settings[\thebuggenie\core\framework\Settings::SETTING_UPLOAD_EXTENSIONS_LIST] = 'exe,bat,php,asp,jsp';
            $settings[\thebuggenie\core\framework\Settings::SETTING_UPLOAD_STORAGE] = 'files';
            $settings[\thebuggenie\core\framework\Settings::SETTING_UPLOAD_LOCAL_PATH] = THEBUGGENIE_PATH . 'files/';
            $settings[\thebuggenie\core\framework\Settings::SETTING_UPLOAD_ALLOW_IMAGE_CACHING] = false;
            $settings[\thebuggenie\core\framework\Settings::SETTING_UPLOAD_DELIVERY_USE_XSEND] = false;
            $settings[\thebuggenie\core\framework\Settings::SETTING_TBG_NAME] = 'The Bug Genie';
            $settings[\thebuggenie\core\framework\Settings::SETTING_SYNTAX_HIGHLIGHT_DEFAULT_LANGUAGE] = 'html';
            $settings[\thebuggenie\core\framework\Settings::SETTING_SYNTAX_HIGHLIGHT_DEFAULT_NUMBERING] = '3';
            $settings[\thebuggenie\core\framework\Settings::SETTING_SYNTAX_HIGHLIGHT_DEFAULT_INTERVAL] = '10';
            $settings[\thebuggenie\core\framework\Settings::SETTING_ICONSET] = 'oxygen';
            $settings[\thebuggenie\core\framework\Settings::SETTING_SERVER_TIMEZONE] = date_default_timezone_get();
            $settings[\thebuggenie\core\framework\Settings::SETTING_ELEVATED_LOGIN_DISABLED] = true;

            $scope_id = $scope->getID();
            foreach ($settings as $settings_name => $settings_val)
            {
                $this->saveSetting($settings_name, 'core', $settings_val, 0, $scope_id);
            }
        }

        public function getFileIds()
        {
            $crit = $this->getCriteria();
            $file_id_settings = [
                framework\Settings::SETTING_FAVICON_ID,
                framework\Settings::SETTING_HEADER_ICON_ID
            ];
            $crit->addWhere(self::NAME, $file_id_settings, Criteria::DB_IN);
            $crit->addSelectionColumn(self::VALUE, 'file_id');

            $res = $this->doSelect($crit);
            $file_ids = [];
            if ($res) {
                while ($row = $res->getNextRow()) {
                    $file_ids[$row['file_id']] = $row['file_id'];
                }
            }

            return $file_ids;
        }

    }

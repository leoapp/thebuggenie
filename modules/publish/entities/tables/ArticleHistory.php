<?php

    namespace thebuggenie\modules\publish\entities\tables;

    use thebuggenie\core\framework,
        thebuggenie\core\entities\tables\ScopedTable,
        thebuggenie\core\entities\tables\Projects,
        b2db\Core,
        b2db\Criteria;

    /**
     * @Table(name="articlehistory")
     */
    class ArticleHistory extends ScopedTable
    {

        const B2DB_TABLE_VERSION = 1;
        const B2DBNAME = 'articlehistory';
        const ID = 'articlehistory.id';
        const ARTICLE_NAME = 'articlehistory.article_name';
        const OLD_CONTENT = 'articlehistory.old_content';
        const NEW_CONTENT = 'articlehistory.new_content';
        const REASON = 'articlehistory.reason';
        const REVISION = 'articlehistory.revision';
        const DATE = 'articlehistory.date';
        const AUTHOR = 'articlehistory.author';
        const SCOPE = 'articlehistory.scope';

        protected function _initialize()
        {
            parent::_setup(self::B2DBNAME, self::ID);
            parent::_addVarchar(self::ARTICLE_NAME, 255);
            parent::_addText(self::OLD_CONTENT, false);
            parent::_addText(self::NEW_CONTENT, false);
            parent::_addVarchar(self::REASON, 255);
            parent::_addInteger(self::DATE, 10);
            parent::_addInteger(self::REVISION, 10);
            parent::_addForeignKeyColumn(self::AUTHOR, \thebuggenie\core\entities\tables\Users::getTable(), \thebuggenie\core\entities\tables\Users::ID);
        }

        protected function _getNextRevisionNumberForArticle($article_name)
        {
            $crit = $this->getCriteria();
            $crit->addSelectionColumn(self::REVISION, 'next_revision', Criteria::DB_MAX, '', '+1');
            $crit->addWhere(self::ARTICLE_NAME, $article_name);
            $crit->addWhere(self::SCOPE, framework\Context::getScope()->getID());

            $row = $this->doSelectOne($crit);
            return ($row->get('next_revision')) ? $row->get('next_revision') : 1;
        }

        public function deleteHistoryByArticle($article_name)
        {
            $crit = $this->getCriteria();
            $crit->addWhere(self::ARTICLE_NAME, $article_name);
            $crit->addWhere(self::SCOPE, framework\Context::getScope()->getID());
            $res = $this->doDelete($crit);
        }

        public function addArticleHistory($article_name, $old_content, $new_content, $user_id, $reason = null)
        {
            if (!Core::isTransactionActive()) $transaction = Core::startTransaction();
            $crit = $this->getCriteria();
            $crit->addInsert(self::ARTICLE_NAME, $article_name);
            $crit->addInsert(self::AUTHOR, $user_id);
            $revision_number = $this->_getNextRevisionNumberForArticle($article_name);
            $crit->addInsert(self::REVISION, $revision_number);

            if (!($revision_number == 1 && $old_content == $new_content))
            {
                $crit->addInsert(self::OLD_CONTENT, $old_content);
            }
            else
            {
                $crit->addInsert(self::OLD_CONTENT, '');
            }
            $crit->addInsert(self::NEW_CONTENT, $new_content);

            if ($reason !== null)
            {
                $crit->addInsert(self::REASON, $reason);
            }

            $crit->addInsert(self::SCOPE, framework\Context::getScope()->getID());
            $crit->addInsert(self::DATE, NOW);

            $res = $this->doInsert($crit);
            if (isset($transaction)) $transaction->commitAndEnd();

            return $revision_number;
        }

        public function getHistoryByArticleName($article_name)
        {
            $crit = $this->getCriteria();
            $crit->addWhere(self::ARTICLE_NAME, $article_name);
            $crit->addWhere(self::SCOPE, framework\Context::getScope()->getID());
            $crit->addOrderBy(self::REVISION, 'desc');

            $res = $this->doSelect($crit);

            return $res;
        }

        public function getUserIDsByArticleName($article_name)
        {
            $crit = $this->getCriteria();
            $crit->addWhere(self::ARTICLE_NAME, $article_name);
            $crit->addWhere(self::SCOPE, framework\Context::getScope()->getID());
            $crit->addSelectionColumn(self::AUTHOR);

            $res = $this->doSelect($crit);
            $uids = array();

            if ($res)
            {
                while ($row = $res->getNextRow())
                {
                    $a_id = $row[self::AUTHOR];
                    if ($a_id > 0)
                        $uids[$a_id] = $a_id;
                }
            }

            return $uids;
        }

        public function getRevisionContentFromArticleName($article_name, $from_revision, $to_revision = null)
        {
            $crit = $this->getCriteria();
            $crit->addWhere(self::ARTICLE_NAME, $article_name);
            $crit->addWhere(self::SCOPE, framework\Context::getScope()->getID());
            $ctn = $crit->returnCriterion(self::REVISION, $from_revision);
            if ($to_revision !== null)
            {
                $ctn->addOr(self::REVISION, $to_revision);
            }
            $crit->addWhere($ctn);

            $res = $this->doSelect($crit);

            if ($res)
            {
                $retval = array();
                while ($row = $res->getNextRow())
                {
                    $author = ($row->get(self::AUTHOR)) ? new \thebuggenie\core\entities\User($row->get(self::AUTHOR)) : null;
                    $retval[$row->get(self::REVISION)] = array('old_content' => $row->get(self::OLD_CONTENT), 'new_content' => $row->get(self::NEW_CONTENT), 'date' => $row->get(self::DATE), 'author' => $author);
                }

                return ($to_revision !== null) ? $retval : $retval[$from_revision];
            }
            else
            {
                return null;
            }
        }

        public function removeArticleRevisionsSince($article_name, $revision)
        {
            $crit = $this->getCriteria();
            $crit->addWhere(self::ARTICLE_NAME, $article_name);
            $crit->addWhere(self::SCOPE, framework\Context::getScope()->getID());
            $crit->addWhere(self::REVISION, $revision, Criteria::DB_GREATER_THAN);
            $res = $this->doDelete($crit);
        }

        /**
         * Returns all article history entries tied-in to a specific
         * user. Entries are sorted based on date, with newest entry at
         * beginning.
         *
         * @param \thebuggenie\core\entities\User $user User for which to fetch article history.
         *
         * @return \b2db\Resultset Result set with requested rows.
         */
        public function getByUser($user)
        {
            $crit = $this->getCriteria();
            $crit->addWhere(self::AUTHOR, $user->getID());
            $crit->addWhere(self::SCOPE, framework\Context::getScope()->getID());
            $crit->addOrderBy(self::DATE, 'desc');

            $res = $this->doSelect($crit);

            return $res;
        }

        /**
         * Returns an array with user IDs of all users that have contributed to
         * the project or global wiki.
         *
         * @param \thebuggenie\core\entities\Project $project
         *   Project for which to obtain list of contributors. If it is necessry
         *   to obtain contributors for global namespace, use null.
         *
         * @retval array
         *   Array with user IDs of all contributors to the requested project or
         *   global wiki.
         */
        public function getContributorIDsByProject($project)
        {
            // All user IDs will get stored here.
            $result = array();

            $crit = $this->getCriteria();
            $crit->addWhere(self::SCOPE, framework\Context::getScope()->getID());
            $crit->addSelectionColumn(self::AUTHOR);
            $crit->setDistinct();

            // If we need to look-up global wiki contributions, then we must
            // fetch list of all projects and exclude them from search.
            if ($project === null)
            {
                $projects_table = Projects::getTable();

                $project_crit = $projects_table->getCriteria();
                $project_crit->addWhere(Projects::SCOPE, framework\Context::getScope()->getID());
                $project_crit->addSelectionColumn(Projects::KEY);

                $project_res = $projects_table->doSelect($project_crit);

                if ($project_res)
                {
                    while ($project_row = $project_res->getNextRow())
                    {
                        $crit->addWhere(self::ARTICLE_NAME, "{$project_row[Projects::KEY]}:%", Criteria::DB_NOT_LIKE);
                    }
                }
            }
            else
            {
                $crit->addWhere(self::ARTICLE_NAME, "{$project->getKey()}:%", Criteria::DB_LIKE);
            }

            $res = $this->doSelect($crit);

            if ($res)
            {
                while ($row = $res->getNextRow())
                {
                    $result[] = $row[self::AUTHOR];
                }
            }

            return $result;
        }
    }

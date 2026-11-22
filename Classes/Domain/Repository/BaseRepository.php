<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Repository;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

class BaseRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{
    protected $tableName;

    /**
     * The records are not limited to the storage page of the plugin (Extbase default), the storage
     * folders are set with plugin.tx_cal.settings.pidList (see ContentController::getStoragePageIds()).
     * Repositories overriding this method must call parent::initializeObject().
     */
    public function initializeObject()
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

}

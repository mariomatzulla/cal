<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Repository;

class LocationRepository extends \MarioMatzulla\Cal\Domain\Repository\BaseRepository
{
    public function initializeObject()
    {
        $this->objectType = 'MarioMatzulla\Cal\Domain\Model\Location';
    }

    /**
     * @param array<int> $storagePageIds only records stored in these pages (empty: no limitation)
     */
    public function findLocation($uid, array $storagePageIds = [])
    {
        $query = $this->createQuery();
        $conditions = [ ];
        $conditions [] = $query->logicalAnd($query->equals('uid', $uid));
        if ($storagePageIds !== []) {
            $conditions [] = $query->in('pid', $storagePageIds);
        }
        return $query->matching($query->logicalAnd(...$conditions))->execute(false);
    }

}

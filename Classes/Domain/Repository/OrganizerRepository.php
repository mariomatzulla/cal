<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Repository;

class OrganizerRepository extends \MarioMatzulla\Cal\Domain\Repository\BaseRepository
{
    public function initializeObject()
    {
        $this->objectType = 'MarioMatzulla\Cal\Domain\Model\Organizer';
    }

    public function findOrganizer($uid)
    {
        $query = $this->createQuery();
        $conditions = [ ];
        $conditions [] = $query->logicalAnd($query->equals('uid', $uid));
        return $query->matching($query->logicalAnd(...$conditions))->execute(false);
    }

}

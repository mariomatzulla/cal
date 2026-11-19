<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

use MarioMatzulla\Cal\Domain\Repository\FrontendUserRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class BaseService
{
    public function __construct(private FrontendUserRepository $frontendUserRepository) {}

    /**
     * @return array<mixed>|null
     */
    protected function getLoggedInUser()
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $userId = $context->getPropertyFromAspect('frontend.user', 'id');
        if (! $userId) {
            return null;
        }
        return $this->frontendUserRepository->findByUid($userId);
    }
}

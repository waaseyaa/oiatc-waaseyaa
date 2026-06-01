<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Explicit access posture for the `doc_chunk` retrieval content.
 *
 * Doc chunks are extracted verbatim from already-public pages, so reading them
 * carries no new exposure: view is world-readable. Writes are an ingestion
 * concern (the app:ingest-docs command and any future admin tooling), so
 * create/update/delete require the `administer doc_chunk` permission; anonymous
 * and unprivileged accounts get Neutral, which the handler treats as denied
 * (it requires isAllowed()). Fails closed on writes.
 */
#[PolicyAttribute(entityType: 'doc_chunk')]
final class DocChunkAccessPolicy implements AccessPolicyInterface
{
    /** Permission required to ingest, edit, or delete doc chunks. */
    public const PERMISSION_MANAGE = 'administer doc_chunk';

    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'doc_chunk';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($operation === 'view') {
            return AccessResult::allowed('doc_chunk derives from already-public pages');
        }

        return $account->hasPermission(self::PERMISSION_MANAGE)
            ? AccessResult::allowed('manage permission holder')
            : AccessResult::neutral('doc_chunk writes require the manage permission');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        return $account->hasPermission(self::PERMISSION_MANAGE)
            ? AccessResult::allowed('manage permission holder')
            : AccessResult::neutral('creating a doc_chunk requires the manage permission');
    }
}

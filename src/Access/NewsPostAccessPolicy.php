<?php

declare(strict_types=1);

namespace App\Access;

use App\Entity\NewsPost;
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Explicit access posture for the `news_post` JSON:API surface (/api/news_post).
 *
 * Without a policy, news_post inherits the framework's fail-closed default:
 * every operation resolves to Neutral, and EntityAccessHandler/JsonApiController
 * require isAllowed(), so even published posts are invisible to the read API.
 * This policy makes the intended posture explicit rather than inherited:
 *
 *   - view:   published posts are world-readable; unpublished (draft) posts are
 *             visible only to holders of the manage permission.
 *   - create / update / delete: require the `administer news_post` permission.
 *             Anonymous and unprivileged accounts receive Neutral, which the
 *             handler treats as denied (it requires isAllowed()). Fails closed.
 *
 * The public SSR pages (/news, /news/{slug}, RSS) do NOT go through this policy:
 * NewsController reads via EntityRepository::findBy (storage layer) and filters
 * isPublished() in PHP. This policy governs only the JSON:API surface.
 *
 * @api
 */
#[PolicyAttribute(entityType: 'news_post')]
final class NewsPostAccessPolicy implements AccessPolicyInterface
{
    /** Permission required to author, edit, or delete news posts. */
    public const PERMISSION_MANAGE = 'administer news_post';

    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'news_post';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($operation === 'view') {
            if ($entity instanceof NewsPost && $entity->isPublished()) {
                return AccessResult::allowed('published news_post is public');
            }

            return $account->hasPermission(self::PERMISSION_MANAGE)
                ? AccessResult::allowed('manage permission may view unpublished posts')
                : AccessResult::neutral('unpublished news_post is hidden from the public');
        }

        // update / delete (and any other write-like operation).
        return $account->hasPermission(self::PERMISSION_MANAGE)
            ? AccessResult::allowed('manage permission holder')
            : AccessResult::neutral('news_post writes require the manage permission');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        return $account->hasPermission(self::PERMISSION_MANAGE)
            ? AccessResult::allowed('manage permission holder')
            : AccessResult::neutral('creating a news_post requires the manage permission');
    }
}

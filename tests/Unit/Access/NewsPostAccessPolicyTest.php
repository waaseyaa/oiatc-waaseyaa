<?php

declare(strict_types=1);

namespace App\Tests\Unit\Access;

use App\Access\NewsPostAccessPolicy;
use App\Entity\NewsPost;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

final class NewsPostAccessPolicyTest extends TestCase
{
    private NewsPostAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new NewsPostAccessPolicy();
    }

    #[Test]
    public function anonymous_may_read_a_published_post(): void
    {
        $result = $this->policy->access($this->post(published: true), 'view', $this->anonymous());

        self::assertTrue($result->isAllowed(), 'A published news_post must be world-readable.');
    }

    #[Test]
    public function anonymous_may_not_read_a_draft_post(): void
    {
        $result = $this->policy->access($this->post(published: false), 'view', $this->anonymous());

        self::assertFalse($result->isAllowed(), 'A draft news_post must not be visible to the public.');
    }

    #[Test]
    public function anonymous_writes_fail_closed(): void
    {
        $anon = $this->anonymous();
        $post = $this->post(published: true);

        self::assertFalse(
            $this->policy->createAccess('news_post', 'news_post', $anon)->isAllowed(),
            'Anonymous POST /api/news_post must be denied.',
        );
        self::assertFalse(
            $this->policy->access($post, 'update', $anon)->isAllowed(),
            'Anonymous PATCH /api/news_post/{id} must be denied.',
        );
        self::assertFalse(
            $this->policy->access($post, 'delete', $anon)->isAllowed(),
            'Anonymous DELETE /api/news_post/{id} must be denied.',
        );
    }

    #[Test]
    public function the_manage_permission_allows_writes(): void
    {
        $admin = $this->account(authenticated: true, permissions: [NewsPostAccessPolicy::PERMISSION_MANAGE]);
        $post = $this->post(published: true);

        self::assertTrue($this->policy->createAccess('news_post', 'news_post', $admin)->isAllowed());
        self::assertTrue($this->policy->access($post, 'update', $admin)->isAllowed());
        self::assertTrue($this->policy->access($post, 'delete', $admin)->isAllowed());
    }

    private function post(bool $published): NewsPost
    {
        return new NewsPost([
            'title' => 'Example',
            'slug' => 'example',
            'body' => '<p>Example</p>',
            'published_at' => 1,
            'related_explainer' => 'where-your-data-lives',
            'status' => $published,
        ]);
    }

    private function anonymous(): AccountInterface
    {
        return $this->account(authenticated: false, permissions: []);
    }

    /**
     * @param list<string> $permissions
     */
    private function account(bool $authenticated, array $permissions): AccountInterface
    {
        return new class ($authenticated, $permissions) implements AccountInterface {
            /**
             * @param list<string> $permissions
             */
            public function __construct(
                private readonly bool $authenticated,
                private readonly array $permissions,
            ) {}

            public function id(): int
            {
                return $this->authenticated ? 1 : 0;
            }

            public function hasPermission(string $permission): bool
            {
                return in_array($permission, $this->permissions, true);
            }

            public function getRoles(): array
            {
                return [];
            }

            public function isAuthenticated(): bool
            {
                return $this->authenticated;
            }
        };
    }
}

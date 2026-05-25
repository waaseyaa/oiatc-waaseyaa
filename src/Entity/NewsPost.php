<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\Hydration\HydratableFromStorageInterface;
use Waaseyaa\Entity\Hydration\HydrationContext;

/**
 * A short, time-stamped news update tied to one explainer.
 *
 * News posts age; explainers stay canonical. Defining this entity gives us
 * storage, validation, a JSON:API CRUD surface (/api/news_post), and admin
 * authoring for free — the public /news pages and RSS are themed separately.
 *
 * Field values live in the ContentEntityBase value bag (read via get()/set());
 * the typed properties below exist so the #[Field] attributes are discovered.
 */
#[ContentEntityType(id: 'news_post', label: 'News post', description: 'Short, time-stamped updates tied to an explainer.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'title')]
final class NewsPost extends ContentEntityBase implements HydratableFromStorageInterface
{
    #[Field(label: 'Title', description: 'Headline of the post.', required: true, settings: ['weight' => 0])]
    public string $title = '';

    #[Field(label: 'Slug', description: 'URL-friendly id, used at /news/{slug}.', required: true, settings: ['weight' => 1])]
    public string $slug = '';

    #[Field(type: 'text', label: 'Body', description: 'Post body (HTML or plain text).', required: true, settings: ['weight' => 2])]
    public string $body = '';

    #[Field(type: 'integer', label: 'Published at', description: 'Publication time (unix timestamp).', required: true, settings: ['weight' => 3, 'subtype' => 'timestamp'])]
    public ?int $published_at = null;

    #[Field(label: 'Related explainer', description: 'Slug of the explainer this post updates, e.g. massey-solar-project.', required: true, settings: ['weight' => 4])]
    public string $related_explainer = '';

    #[Field(type: 'boolean', label: 'Published', description: 'Whether the post is visible.', default: 1, settings: ['weight' => 5])]
    public bool $status = true;

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $entityKeys
     * @param array<string, mixed> $fieldDefinitions
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function make(array $values): self
    {
        return new self($values);
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromStorage(array $values, HydrationContext $context): static
    {
        return new self(
            values: $values,
            entityTypeId: $context->entityTypeId,
            entityKeys: $context->entityKeys,
            fieldDefinitions: [],
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function duplicateInstance(array $values): static
    {
        return new static(
            values: $values,
            entityTypeId: $this->getEntityTypeId(),
            entityKeys: $this->entityKeys,
            fieldDefinitions: $this->getFieldDefinitions(),
        );
    }

    public function getTitle(): string
    {
        return (string) ($this->get('title') ?? '');
    }

    public function getSlug(): string
    {
        return (string) ($this->get('slug') ?? '');
    }

    public function getBody(): string
    {
        return (string) ($this->get('body') ?? '');
    }

    public function getPublishedAt(): int
    {
        return (int) ($this->get('published_at') ?? 0);
    }

    public function getRelatedExplainer(): string
    {
        return (string) ($this->get('related_explainer') ?? '');
    }

    public function isPublished(): bool
    {
        return (bool) ($this->get('status') ?? false);
    }
}

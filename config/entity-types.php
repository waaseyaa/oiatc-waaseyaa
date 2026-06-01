<?php

declare(strict_types=1);

/**
 * Application-specific entity types.
 *
 * Return an array of EntityType instances to register additional entity
 * types beyond those provided by Waaseyaa packages.
 *
 * Example:
 *   return [
 *       new \Waaseyaa\Entity\EntityType(
 *           id: 'product',
 *           label: 'Product',
 *           class: \App\Entity\Product::class,
 *           keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
 *       ),
 *   ];
 */

return [
    new \Waaseyaa\Entity\EntityType(
        id: 'news_post',
        label: 'News post',
        class: \App\Entity\NewsPost::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
    ),
    new \Waaseyaa\Entity\EntityType(
        id: 'doc_chunk',
        label: 'Doc chunk',
        class: \App\Entity\DocChunk::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
    ),

    // Anokii relational graph: communities are vantage points onto one shared
    // graph; resources cross community lines and are never copied. Cross-entity
    // references are stored by stable slug. See src/Support/GraphRetriever.php.
    new \Waaseyaa\Entity\EntityType(
        id: 'community',
        label: 'Community',
        class: \App\Entity\Community::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
    ),
    new \Waaseyaa\Entity\EntityType(
        id: 'place',
        label: 'Place',
        class: \App\Entity\Place::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
    ),
    new \Waaseyaa\Entity\EntityType(
        id: 'organization',
        label: 'Organization',
        class: \App\Entity\Organization::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
    ),
    new \Waaseyaa\Entity\EntityType(
        id: 'service',
        label: 'Service',
        class: \App\Entity\Service::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
    ),
    new \Waaseyaa\Entity\EntityType(
        id: 'project',
        label: 'Project',
        class: \App\Entity\Project::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
    ),
    new \Waaseyaa\Entity\EntityType(
        id: 'topic',
        label: 'Topic',
        class: \App\Entity\Topic::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
    ),
];

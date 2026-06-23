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

    // The Anokii relational graph (community, place, organization, service,
    // project, topic) and the doc_chunk RAG corpus are now registered by the
    // package's Anokii\Provider\CoIntelligenceServiceProvider; the app no longer
    // forks them. Only news_post is app-owned.
];

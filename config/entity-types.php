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
];

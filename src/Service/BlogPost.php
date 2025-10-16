<?php
namespace App\Service;

class BlogPost
{
    public string $title;
    public string $permalink;
    public ?int $yearsAgo = null;
    public string $date;
    public ?string $excerpt = null;

        public static function fromArray(array $data): self
        {
            $instance = new self();
            $instance->title = $data['post_title'] ?? '';
            $instance->permalink = $data['permalink'] ?? '';
            $instance->date = $data['post_date'] ?? '';
            $instance->excerpt = $data['post_excerpt'] ?? null;
            $instance->yearsAgo = isset($data['years_ago']) ? (int)$data['years_ago'] : null;
            return $instance;
        }
}

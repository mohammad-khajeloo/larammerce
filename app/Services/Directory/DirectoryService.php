<?php

namespace App\Services\Directory;

use App\Exceptions\Directory\DirectoryNotFoundException;
use App\Helpers\Common\StringHelper;
use App\Models\Directory;
use Exception;
use Illuminate\Support\Facades\Cache;
use Ixudra\Curl\Facades\Curl;
use stdClass;

class DirectoryService {
    /**
     * @throws DirectoryNotFoundException
     */
    public static function findDirectoryById(int $directory_id): Directory {
        try {
            return Directory::findOrFail($directory_id);
        } catch (Exception $e) {
            throw new DirectoryNotFoundException("The directory with id `{$directory_id}` not found int the database.");
        }
    }

    public static function clearCache(): void {
        Cache::tags([Directory::class])->flush();
    }

    public static function buildDirectoriesTree(?Directory $root = null, array $conditions = [], array $order = []): array {
        $cache_key = StringHelper::getCacheKey([static::class, __FUNCTION__], $root?->id ?? 0, json_encode($conditions), json_encode($order));
        if (!Cache::tags([Directory::class])->has($cache_key)) {
            $directories = Directory::permitted()->where($conditions)
                ->orderBy($order["column"] ?? "priority", $order["direction"] ?? "ASC")->get();
            $branch = [];
            $parts = [];
            $map = [];

            foreach ($directories as $directory) {
                $map[$directory->id] = $directory;
                $directory->setRelation("directories", []);
                if (!isset($parts[$directory->directory_id]))
                    $parts[$directory->directory_id] = [];
                $parts[$directory->directory_id][] = $directory;
            }

            foreach ($parts as $parent_id => $children) {
                if (isset($map[$parent_id]))
                    $map[$parent_id]->setRelation("directories", $children);
                else {
                    $branch = array_merge($branch, $children);
                }
            }

            Cache::tags([Directory::class])->put($cache_key, ($root == null ? $branch : ($map[$root->id]->directories ?? [])), 600);
        }

        return Cache::tags([Directory::class])->get($cache_key);
    }

    public static function syncWithUpstream(Directory $directory): void {
        $metadata = $directory->metadata;
        if (!filter_var($metadata, FILTER_VALIDATE_URL)) {
            return;
        }

        $curl_result = Curl::to(env("STOCK_HOST", "http://localhost:8080") . "/api/root?url=" . base64_encode($metadata))
            ->asJson()
            ->get();

        foreach ($curl_result as $sub_node) {
            static::createProductDirectoryByNodeData($directory, $sub_node);
        }
    }

    private static function createProductDirectoryByNodeData(Directory $parent_directory, stdClass $node_data) {
        $title = strip_tags($node_data?->data?->title);
        $url_part = static::buildUrlPartFromString($title);

        $head_directory = $parent_directory->directories()->where("url_part", $url_part)->first();
        if (is_null($head_directory)) {
            /** @var Directory $head_directory */
            $head_directory = $parent_directory->directories()->create([
                "title" => $title, "url_part" => $url_part, "is_internal_link" => false, "is_anonymously_accessible" => true,
                "has_web_page" => false, "priority" => 0, "content_type" => 3, "directory_id" => null,
                "show_in_navbar" => true, "show_in_footer" => false, "cover_image_path" => null, "description" => "",
                "data_type" => 1, "show_in_app_navbar" => false, "is_location_limited" => false,
                "cmc_id" => null, "force_show_landing" => false, "inaccessibility_type" => 1, "notice" => "",
                "metadata" => $node_data?->data?->url
            ]);
            $head_directory->setUrlFull();
        }

        foreach ($node_data->sub_nodes as $sub_node) {
            static::createProductDirectoryByNodeData($head_directory, $sub_node);
        }
    }

    public static function buildUrlPartFromString(string $title): string {
        $title = preg_replace('!\s+!', "-", strtolower($title));
        return preg_replace("/[^a-zA-Z0-9\-]/", "", $title);
    }
}
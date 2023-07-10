<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Session_Driver_Mongodb extends Session_Driver
{
    function gc($maxlifetime): int|false
    {
        $threashold = (new DateTime())->modify("-{$maxlifetime} sec");
        $threashold_msec = intval($threashold->format("U.u") * 1000);

        $collection = static::get_collection();
        $query = [
            'updated_at' => ['$lt' => new \MongoDB\BSON\UTCDateTime($threashold_msec)],
        ];
        $collection->deleteMany($query);

        return true;
    }

    function destroy($id): bool
    {
        $collection = static::get_collection();
        $collection->deleteOne(['id' => $id]);

        return true;
    }

    private function get_collection_name(): string
    {
        $collection_name = $this->config['collection'] ?? 'sessions';

        return $collection_name;
    }

    private function get_collection(): \MongoDB\Collection
    {
        $conn = (\Mongodb_Connection::instance())->get_connection();
        $collection_name = $this->get_collection_name();
        $collection = $conn->selectCollection($collection_name);

        return $collection;
    }

    function write($id, $data): bool
    {
        list($encoded_data, $hash, $original_data) = $this->encode_data($data);

        $collection = static::get_collection();
        try {
            $collection->updateOne(['id' => $id], [
                '$set' => [
                    'id' => $id,
                    'php_data' => $encoded_data,
                    'data' => $original_data,
                    'hash' => $hash,
                ],
                '$currentDate' => [
                    'updated_at' => true,
                ],
            ], [
                'upsert' => true,
            ]);
        } catch (Exception $e) {
            Log::error("ログデータ保存時にエラーが発生しました", $e);
        }

        return true;
    }

    function read($id): string|false
    {
        $collection = static::get_collection();
        $r = $collection->findOne(['id' => $id]);

        $data = $this->decode_data($r->php_data ?? null, $r->hash ?? null);

        return strval($data);
    }

    function close(): bool
    {
        return true;
    }

    function open($savePath, $sessionName): bool
    {
        $collection = $this->get_collection();

        $r = $collection->listIndexes();

        $index_name = "id_unique";

        $index_not_found = true;
        foreach ($r as $item) {
            if (($item['name'] ?? null) === $index_name) {
                $index_not_found = false;
                break;
            }
        }
        if ($index_not_found) {
            $collection->createIndex(['id' => 1], [
                'name' => $index_name,
                'unique' => true,
            ]);
        }

        return true;
    }
}

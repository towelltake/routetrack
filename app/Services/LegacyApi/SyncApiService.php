<?php

namespace App\Services\LegacyApi;

use Illuminate\Support\Facades\DB;

class SyncApiService
{
    private array $inventoryHeaderMap = [];

    private array $salesOrderHeaderMap = [];

    private array $invoiceHeaderMap = [];

    private array $arHeaderMap = [];

    private array $columnCache = [];

    private array $columnMetaCache = [];

    public function __construct(private readonly LegacyProcedureService $support)
    {
    }

    public function sync(array $params): array
    {
        $result = [];

        $this->persistSimpleSection($result, $params, 'customeroperationscontrol', 'customeroperationscontrol', ['routekey', 'visitkey'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'visitkey' => $row['visitkey'] ?? '',
            'customercode' => $row['customercode'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 'customermaster', 'customermaster', ['customercode'], fn ($row) => [
            'customercode' => $row['customercode'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 'routemaster', 'routemaster', ['routecode'], fn ($row) => [
            'routecode' => $row['routecode'] ?? '',
        ], function (array $row): array {
            $row['mdat'] = now()->toDateString();

            return $row;
        });

        $this->persistInvoiceHeader($result, $params);
        $this->persistInvoiceDetail($result, $params);
        $this->persistInvoiceRxDetail($result, $params);

        $this->persistSalesOrderHeader($result, $params);
        $this->persistSalesOrderDetail($result, $params);

        $this->persistSimpleSection($result, $params, 'orderrxddetail', 'orderrxddetail', ['routekey', 'visitkey', 'transactionkey', 'itemcode', 'itemtransactiontype', 'reasoncode'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'visitkey' => $row['visitkey'] ?? '',
            'itemcode' => $row['itemcode'] ?? '',
        ]);

        $this->persistPromotionDetail($result, $params);

        $this->persistSimpleSection($result, $params, 'batchexpirydetail', 'batchexpirydetail', ['routekey', 'visitkey', 'batchdetailkey', 'itemcode'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'visitkey' => $row['visitkey'] ?? '',
            'batchdetailkey' => $row['batchdetailkey'] ?? '',
        ]);

        $this->persistArHeader($result, $params);
        $this->persistArDetail($result, $params);

        $this->persistSimpleSection($result, $params, 'cashcheckdetail', 'cashcheckdetail', ['routekey', 'visitkey', 'hhctransactionkey'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'visitkey' => $row['visitkey'] ?? '',
        ], function (array $row): array {
            $row['checkdate'] = $this->normalizeDate($row['checkdate'] ?? '');
            $row['checknumber'] = substr((string) ($row['checknumber'] ?? ''), 0, 10);

            return $row;
        });

        $this->persistSimpleSection($result, $params, 'customerinvoice', 'customerinvoice', ['transactionkey'], fn ($row) => [
            'transactionkey' => $row['transactionkey'] ?? '',
        ]);

        $this->persistInventoryHeader($result, $params);
        $this->persistInventoryDetail($result, $params);

        $this->persistSimpleSection($result, $params, 'inventorysummarydetail', 'inventorysummarydetail', ['inventorykey', 'itemcode', 'routekey'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'itemcode' => $row['itemcode'] ?? '',
            'inventorykey' => $row['inventorykey'] ?? '',
        ], function (array $row): array {
            $row['mdat'] = $this->normalizeTimestampField($row['mdat'] ?? null);
            if ($row['mdat'] === null) {
                $row['mdat'] = now()->toDateTimeString();
            }

            return $row;
        });

        $this->persistSimpleSection($result, $params, 'nonservicedcustomer', 'nonservicedcustomer', ['routekey', 'customercode'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'customercode' => $row['customercode'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 'surveyauditdetail', 'surveyauditdetail', ['routekey', 'visitkey', 'surveydefkey', 'surveypage', 'surveyindex'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'visitkey' => $row['visitkey'] ?? '',
            'surveydefkey' => $row['surveydefkey'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 'posequipmentchangedetail', 'posequipmentchangedetail', ['routekey', 'visitkey', 'itemcode', 'serialnumber'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'visitkey' => $row['visitkey'] ?? '',
            'itemcode' => $row['itemcode'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 'posmaster', 'posmaster', ['itemcode'], fn ($row) => [
            'itemcode' => $row['itemcode'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 'sigcapturedata', 'sigcapturedata', ['routekey', 'visitkey', 'transactionkey'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'visitkey' => $row['visitkey'] ?? '',
            'transactionkey' => $row['transactionkey'] ?? '',
        ], null, true);

        $this->persistSimpleSection($result, $params, 'customerinventorydetail', 'customerinventorydetail', ['routekey', 'visitkey', 'itemcode'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'visitkey' => $row['visitkey'] ?? '',
            'itemcode' => $row['itemcode'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 'routesequencecustomerstatus', 'routesequencecustomerstatus', ['routekey', 'customercode', 'seqweeknumber', 'seqweekday'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'customercode' => $row['customercode'] ?? '',
        ]);

        $this->persistNoSalesHeader($result, $params);

        $this->persistSimpleSection($result, $params, 'routegoal', 'routegoal', ['primary_key'], fn ($row) => [
            'primary_key' => $row['primary_key'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 'customer_foc_balance', 'customer_foc_balance', ['customercode', 'itemcode'], fn ($row) => [
            'customercode' => $row['customercode'] ?? '',
            'itemcode' => $row['itemcode'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 'enddaydetail', 'enddaydetail', ['routekey', 'detailtypecode', 'listtypecode'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 'customerimages', 'customerimages', ['routekey', 'visitkey', 'imageno'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 't_access_override_log', 't_access_override_log', ['routekey', 'visitkey', 'featureid', 'accesskey'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'visitkey' => $row['visitkey'] ?? '',
            'featureid' => $row['featureid'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 'customerdistributioncheck', 'customerdistributioncheck', ['routekey', 'customercode', 'visitkey', 'itemcode'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'customercode' => $row['customercode'] ?? '',
            'visitkey' => $row['visitkey'] ?? '',
            'itemcode' => $row['itemcode'] ?? '',
        ]);

        $this->persistSimpleSection($result, $params, 'customerinventorycheck', 'customerinventorycheck', ['routekey', 'visitkey', 'itemcode'], fn ($row) => [
            'routekey' => $row['routekey'] ?? '',
            'visitkey' => $row['visitkey'] ?? '',
            'itemcode' => $row['itemcode'] ?? '',
        ]);

        if ($this->support->hasTable('tbl_synclog')) {
            $this->safeInsert('tbl_synclog', [
                'userid' => $params['userid'] ?? '',
                'routecode' => $params['routecode'] ?? '',
                'routekey' => $params['routekey'] ?? '',
                'routeclosed' => $params['routeclosed'] ?? '',
                'synctype' => '2',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $this->support->normalizeNulls($result);
    }

    private function persistInventoryHeader(array &$result, array $params): void
    {
        $items = $this->decodeItems($params['inventorytransactionheader'] ?? null);
        $acks = [];

        foreach ($items as $row) {
            $clientDetailKey = (string) ($row['detailkey'] ?? '');

            if (($row['requestdate'] ?? 0) <= 0) {
                $row['requestdate'] = now()->toDateString();
            }

            if (! $this->hasMeaningfulIdentity($row['hhcdocumentnumber'] ?? null)) {
                $row['hhcdocumentnumber'] = $row['documentnumber'] ?? '';
            }

            if (! array_key_exists('record_flag', $row) || $row['record_flag'] === '' || $row['record_flag'] === null) {
                $row['record_flag'] = '1';
            }

            $existingKey = $this->existingInventoryHeaderKey($row);

            if ($existingKey !== null) {
                $row['detailkey'] = $existingKey;
                $this->upsertRow('inventorytransactionheader', ['detailkey'], $row);
                $headerDetailKey = $existingKey;
            } else {
                unset($row['detailkey']);
                $headerDetailKey = $this->insertAndReturnId('inventorytransactionheader', $row, 'detailkey')
                    ?? ($row['detailkey'] ?? '');
            }

            $this->inventoryHeaderMap[$clientDetailKey] = $headerDetailKey;

            if ((int) ($row['transactiontype'] ?? 0) === 1) {
                $this->markStartingLoadProcessed(
                    $row['routecode'] ?? null,
                    $row['loadnumber'] ?? null
                );
            }

            $acks[] = [
                'routekey' => $row['routekey'] ?? '',
                'detailkey' => $clientDetailKey,
                'inventorykey' => $row['inventorykey'] ?? '',
            ];
        }

        $result['inventorytransactionheader'] = $acks;
    }

    private function persistInventoryDetail(array &$result, array $params): void
    {
        $items = $this->decodeItems($params['inventorytransactiondetail'] ?? null);
        $acks = [];

        foreach ($items as $row) {
            $clientDetailKey = $row['detailkey'] ?? '';
            $row['detailkey'] = $this->inventoryHeaderMap[(string) $clientDetailKey] ?? $clientDetailKey;
            if (! array_key_exists('requestedqty', $row) || $row['requestedqty'] === null || $row['requestedqty'] === '') {
                $row['requestedqty'] = $row['quantity'] ?? 0;
            }
            if (($row['expirydate'] ?? 0) <= 0) {
                $row['expirydate'] = '1900-01-01';
            }

            $this->upsertRow('inventorytransactiondetail', ['detailkey', 'transactiontypecode', 'itemcode'], $row);
            $acks[] = [
                'routekey' => $row['routekey'] ?? '',
                'detailkey' => $clientDetailKey,
                'itemcode' => $row['itemcode'] ?? '',
            ];
        }

        $result['inventorytransactiondetail'] = $acks;
    }

    private function persistSalesOrderHeader(array &$result, array $params): void
    {
        $items = $this->decodeItems($params['salesorderheader'] ?? null);
        $acks = [];

        foreach ($items as $row) {
            $row['transactiondate'] = $this->normalizeDateField($row['transactiondate'] ?? null);
            $row['actualtransactiondate'] = $this->normalizeDateField($row['actualtransactiondate'] ?? null);
            $row['orderdeliverydate'] = $this->normalizeDateField($row['orderdeliverydate'] ?? null);
            $row['mdat'] = $this->normalizeTimestampField($row['mdat'] ?? null);
            $row['cdat'] = $this->normalizeTimestampField($row['cdat'] ?? null);

            $clientTransactionKey = (string) ($row['transactionkey'] ?? '');
            if (! $this->hasMeaningfulIdentity($row['hhctransactionkey'] ?? null)) {
                $row['hhctransactionkey'] = $clientTransactionKey;
            }

            $existingKey = $this->existingSalesOrderHeaderKey($row);

            if ($existingKey !== null) {
                $row['transactionkey'] = $existingKey;
                $this->upsertRow('salesorderheader', ['transactionkey'], $row);
                $serverTransactionKey = $existingKey;
            } else {
                unset($row['transactionkey']);
                $serverTransactionKey = $this->insertAndReturnId('salesorderheader', $row, 'transactionkey');
            }

            $this->salesOrderHeaderMap[$this->salesOrderMapKey(
                $row['routekey'] ?? null,
                $row['visitkey'] ?? null,
                $clientTransactionKey
            )] = $serverTransactionKey;

            $acks[] = [
                'routekey' => $row['routekey'] ?? '',
                'visitkey' => $row['visitkey'] ?? '',
            ];
        }

        $result['salesorderheader'] = $acks;
    }

    private function persistSalesOrderDetail(array &$result, array $params): void
    {
        $items = $this->decodeItems($params['salesorderdetail'] ?? null);
        $acks = [];

        foreach ($items as $row) {
            $clientTransactionKey = (string) ($row['transactionkey'] ?? '');
            $mappedKey = $this->salesOrderHeaderMap[$this->salesOrderMapKey(
                $row['routekey'] ?? null,
                $row['visitkey'] ?? null,
                $clientTransactionKey
            )] ?? null;

            if ($mappedKey !== null) {
                $row['transactionkey'] = $mappedKey;
            }

            $this->upsertRow('salesorderdetail', ['routekey', 'visitkey', 'transactionkey', 'itemcode'], $row);
            $acks[] = [
                'routekey' => $row['routekey'] ?? '',
                'visitkey' => $row['visitkey'] ?? '',
                'itemcode' => $row['itemcode'] ?? '',
            ];
        }

        $result['salesorderdetail'] = $acks;
    }

    private function persistInvoiceHeader(array &$result, array $params): void
    {
        $items = $this->decodeItems($params['invoiceheader'] ?? null);
        $acks = [];

        foreach ($items as $row) {
            if (($row['orderdeliverydate'] ?? 0) == 0) {
                $row['orderdeliverydate'] = now()->toDateString();
            }

            if (! array_key_exists('varianceflag', $row) || $row['varianceflag'] === '' || $row['varianceflag'] === null) {
                $row['varianceflag'] = 0;
            }

            if (! array_key_exists('record_flag', $row) || $row['record_flag'] === '' || $row['record_flag'] === null) {
                $row['record_flag'] = '1';
            }

            $row['transactiondate'] = $this->normalizeDateField($row['transactiondate'] ?? null);
            $row['actualtransactiondate'] = $this->normalizeDateField($row['actualtransactiondate'] ?? null);
            $row['orderdeliverydate'] = $this->normalizeDateField($row['orderdeliverydate'] ?? null);

            $clientTransactionKey = (string) ($row['transactionkey'] ?? '');
            if (! $this->hasMeaningfulIdentity($row['hhctransactionkey'] ?? null)) {
                $row['hhctransactionkey'] = $clientTransactionKey;
            }

            $existingKey = $this->existingInvoiceHeaderKey($row);

            if ($existingKey !== null) {
                $row['transactionkey'] = $existingKey;
                $this->upsertRow('invoiceheader', ['transactionkey'], $row);
                $serverTransactionKey = $existingKey;
            } else {
                unset($row['transactionkey']);
                $serverTransactionKey = $this->insertAndReturnId('invoiceheader', $row, 'transactionkey');
            }

            $this->invoiceHeaderMap[$this->invoiceMapKey(
                $row['routekey'] ?? null,
                $row['visitkey'] ?? null,
                $clientTransactionKey
            )] = $serverTransactionKey;

            $acks[] = [
                'routekey' => $row['routekey'] ?? '',
                'visitkey' => $row['visitkey'] ?? '',
            ];
        }

        $result['invoiceheader'] = $acks;
    }

    private function persistInvoiceDetail(array &$result, array $params): void
    {
        $items = $this->decodeItems($params['invoicedetail'] ?? null);
        $acks = [];

        foreach ($items as $row) {
            $clientTransactionKey = (string) ($row['transactionkey'] ?? '');
            $mappedKey = $this->invoiceHeaderMap[$this->invoiceMapKey(
                $row['routekey'] ?? null,
                $row['visitkey'] ?? null,
                $clientTransactionKey
            )] ?? null;

            if ($mappedKey !== null) {
                $row['transactionkey'] = $mappedKey;
            }

            $this->upsertRow('invoicedetail', ['routekey', 'visitkey', 'itemcode'], $row);
            $acks[] = [
                'routekey' => $row['routekey'] ?? '',
                'visitkey' => $row['visitkey'] ?? '',
                'itemcode' => $row['itemcode'] ?? '',
            ];
        }

        $result['invoicedetail'] = $acks;
    }

    private function persistInvoiceRxDetail(array &$result, array $params): void
    {
        $items = $this->decodeItems($params['invoicerxddetail'] ?? null);
        $acks = [];

        foreach ($items as $row) {
            $clientTransactionKey = (string) ($row['transactionkey'] ?? '');
            $mappedKey = $this->invoiceHeaderMap[$this->invoiceMapKey(
                $row['routekey'] ?? null,
                $row['visitkey'] ?? null,
                $clientTransactionKey
            )] ?? null;

            if ($mappedKey !== null) {
                $row['transactionkey'] = $mappedKey;
            }

            $this->upsertRow('invoicerxddetail', ['routekey', 'visitkey', 'itemcode', 'itemtransactiontype', 'reasoncode'], $row);
            $acks[] = [
                'routekey' => $row['routekey'] ?? '',
                'visitkey' => $row['visitkey'] ?? '',
                'itemcode' => $row['itemcode'] ?? '',
            ];
        }

        $result['invoicerxddetail'] = $acks;
    }

    private function persistPromotionDetail(array &$result, array $params): void
    {
        $items = $this->decodeItems($params['promotiondetail'] ?? null);
        $acks = [];

        foreach ($items as $row) {
            $clientTransactionKey = (string) ($row['transactionkey'] ?? '');
            $mappedKey = $this->invoiceHeaderMap[$this->invoiceMapKey(
                $row['routekey'] ?? null,
                $row['visitkey'] ?? null,
                $clientTransactionKey
            )] ?? null;

            if ($mappedKey !== null) {
                $row['transactionkey'] = $mappedKey;
            }

            $this->upsertRow('promotiondetail', ['routekey', 'visitkey', 'itemcode', 'itemtransactiontype', 'promotiontypecode', 'promotionplannumber'], $row);
            $acks[] = [
                'routekey' => $row['routekey'] ?? '',
                'visitkey' => $row['visitkey'] ?? '',
                'itemcode' => $row['itemcode'] ?? '',
            ];
        }

        $result['promotiondetail'] = $acks;
    }

    private function persistArHeader(array &$result, array $params): void
    {
        $items = $this->decodeItems($params['arheader'] ?? null);
        $acks = [];

        foreach ($items as $row) {
            $row['transactiondate'] = $this->normalizeDateField($row['transactiondate'] ?? null);
            $row['actualtransactiondate'] = $this->normalizeDateField($row['actualtransactiondate'] ?? null);

            if (! array_key_exists('record_flag', $row) || $row['record_flag'] === '' || $row['record_flag'] === null) {
                $row['record_flag'] = '1';
            }

            $clientTransactionKey = (string) ($row['transactionkey'] ?? '');
            if (! $this->hasMeaningfulIdentity($row['hhctransactionkey'] ?? null)) {
                $row['hhctransactionkey'] = $clientTransactionKey;
            }

            $existingKey = $this->existingArHeaderKey($row);

            if ($existingKey !== null) {
                $row['transactionkey'] = $existingKey;
                $this->upsertRow('arheader', ['transactionkey'], $row);
                $serverTransactionKey = $existingKey;
            } else {
                unset($row['transactionkey']);
                $serverTransactionKey = $this->insertAndReturnId('arheader', $row, 'transactionkey');
            }

            $this->arHeaderMap[$this->arMapKey(
                $row['routekey'] ?? null,
                $row['visitkey'] ?? null,
                $clientTransactionKey
            )] = $serverTransactionKey;

            $acks[] = [
                'routekey' => $row['routekey'] ?? '',
                'visitkey' => $row['visitkey'] ?? '',
            ];
        }

        $result['arheader'] = $acks;
    }

    private function persistArDetail(array &$result, array $params): void
    {
        $items = $this->decodeItems($params['ardetail'] ?? null);
        $acks = [];

        foreach ($items as $row) {
            $clientTransactionKey = (string) ($row['transactionkey'] ?? '');
            $mappedKey = $this->arHeaderMap[$this->arMapKey(
                $row['routekey'] ?? null,
                $row['visitkey'] ?? null,
                $clientTransactionKey
            )] ?? null;

            if ($mappedKey === null) {
                $mappedKey = $this->existingArHeaderKey([
                    'routekey' => $row['routekey'] ?? null,
                    'visitkey' => $row['visitkey'] ?? null,
                    'invoicenumber' => $row['invoicenumber'] ?? null,
                    'hhcinvoicenumber' => $row['alternateinvoicenumber'] ?? null,
                    'hhctransactionkey' => $clientTransactionKey,
                ]);
            }

            if ($mappedKey !== null) {
                $row['transactionkey'] = $mappedKey;
            }

            $normalizedInvoiceDate = $this->normalizeTimestampField($row['invoicedate'] ?? null);
            if ($normalizedInvoiceDate === null) {
                $dateOnly = $this->normalizeDateField($row['invoicedate'] ?? null);
                $normalizedInvoiceDate = $dateOnly ? $dateOnly . ' 00:00:00' : null;
            }
            $row['invoicedate'] = $normalizedInvoiceDate;
            $row['referenceno'] = $row['sapchequestatusindicator'] ?? ($row['referenceno'] ?? '');

            $uniqueKeys = ['transactionkey', 'invoicenumber', 'invoicedate'];
            foreach ($uniqueKeys as $key) {
                if (! array_key_exists($key, $row) || $row[$key] === '' || $row[$key] === null) {
                    $uniqueKeys = ['routekey', 'visitkey', 'invoicenumber'];
                    break;
                }
            }

            $this->upsertRow('ardetail', $uniqueKeys, $row);
            $acks[] = [
                'routekey' => $row['routekey'] ?? '',
                'visitkey' => $row['visitkey'] ?? '',
                'transactionkey' => $clientTransactionKey,
            ];
        }

        $result['ardetail'] = $acks;
    }

    private function persistNoSalesHeader(array &$result, array $params): void
    {
        $items = $this->decodeItems($params['nosalesheader'] ?? null);
        $acks = [];

        foreach ($items as $row) {
            $clientTransactionKey = $row['transactionkey'] ?? '';

            // Legacy `sp_ws_get_from_nosalesheader` does not persist the client
            // transaction key into the auto-increment PK column.
            unset($row['transactionkey']);

            $this->safeInsert('nosalesheader', $row);
            $acks[] = [
                'transactionkey' => $clientTransactionKey,
            ];
        }

        $result['nosalesheader'] = $acks;
    }

    private function persistSimpleSection(
        array &$result,
        array $params,
        string $section,
        string $table,
        array $uniqueKeys,
        callable $ackBuilder,
        ?callable $transform = null,
        bool $useRaw = false
    ): void {
        $items = $this->decodeItems($params[$section] ?? null, $useRaw);
        $acks = [];

        foreach ($items as $row) {
            if ($transform) {
                $row = $transform($row);
            }

            $this->upsertRow($table, $uniqueKeys, $row);
            $acks[] = $ackBuilder($row);
        }

        $result[$section] = $acks;
    }

    private function upsertRow(string $table, array $uniqueKeys, array $row): void
    {
        if (! $this->support->hasTable($table)) {
            return;
        }

        $payload = $this->filterColumns($table, $row);

        if ($payload === []) {
            return;
        }

        $where = [];
        foreach ($uniqueKeys as $key) {
            $where[$key] = $payload[$key] ?? $row[$key] ?? null;
        }

        $this->support->table($table)->updateOrInsert($where, $payload);
    }

    private function safeInsert(string $table, array $row): void
    {
        if (! $this->support->hasTable($table)) {
            return;
        }

        $payload = $this->filterColumns($table, $row);

        if ($payload === []) {
            return;
        }

        $this->support->table($table)->insert($payload);
    }

    private function insertAndReturnId(string $table, array $row, string $idColumn): mixed
    {
        if (! $this->support->hasTable($table)) {
            return null;
        }

        $payload = $this->filterColumns($table, $row);

        if ($payload === []) {
            return null;
        }

        return $this->support->table($table)->insertGetId($payload, $idColumn);
    }

    private function lookupValue(string $table, array $where, string $column): mixed
    {
        if (! $this->support->hasTable($table)) {
            return null;
        }

        $resolved = $this->support->resolveTable($table);
        $columns = $this->columnCache[$resolved] ??= array_map(
            static fn ($col) => $col->Field,
            DB::select(sprintf('SHOW COLUMNS FROM `%s`', str_replace('`', '``', $resolved)))
        );

        if (! in_array($column, $columns, true)) {
            return null;
        }

        $query = $this->support->table($table);
        foreach ($where as $key => $value) {
            $query->where($key, $value);
        }

        return $query->value($column);
    }

    private function hasMeaningfulIdentity(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return false;
            }
        }

        if (is_numeric($value) && (float) $value <= 0.0) {
            return false;
        }

        return true;
    }

    private function existingSalesOrderHeaderKey(array $row): mixed
    {
        $hhcKey = $row['hhctransactionkey'] ?? null;
        $routeKey = $row['routekey'] ?? null;
        $visitKey = $row['visitkey'] ?? null;

        if ($this->hasMeaningfulIdentity($hhcKey) && in_array('hhctransactionkey', $this->columnNames('salesorderheader'), true)) {
            $existing = $this->lookupValue('salesorderheader', [
                'routekey' => $routeKey,
                'visitkey' => $visitKey,
                'hhctransactionkey' => $hhcKey,
            ], 'transactionkey');

            if ($existing !== null) {
                return $existing;
            }
        }

        return null;
    }

    private function existingInventoryHeaderKey(array $row): mixed
    {
        $documentNumber = $row['documentnumber'] ?? null;
        if ($this->hasMeaningfulIdentity($documentNumber)) {
            $existing = $this->lookupValue('inventorytransactionheader', [
                'documentnumber' => $documentNumber,
            ], 'detailkey');

            if ($existing !== null) {
                return $existing;
            }
        }

        $hhcDocumentNumber = $row['hhcdocumentnumber'] ?? null;
        if ($this->hasMeaningfulIdentity($hhcDocumentNumber) && in_array('hhcdocumentnumber', $this->columnNames('inventorytransactionheader'), true)) {
            $existing = $this->lookupValue('inventorytransactionheader', [
                'hhcdocumentnumber' => $hhcDocumentNumber,
                'routekey' => $row['routekey'] ?? null,
            ], 'detailkey');

            if ($existing !== null) {
                return $existing;
            }
        }

        return null;
    }

    private function existingInvoiceHeaderKey(array $row): mixed
    {
        $invoiceNumber = $row['invoicenumber'] ?? null;
        if ($this->hasMeaningfulIdentity($invoiceNumber)) {
            $existing = $this->lookupValue('invoiceheader', [
                'invoicenumber' => $invoiceNumber,
            ], 'transactionkey');

            if ($existing !== null) {
                return $existing;
            }
        }

        $hhcInvoiceNumber = $row['hhcinvoicenumber'] ?? null;
        if ($this->hasMeaningfulIdentity($hhcInvoiceNumber) && in_array('hhcinvoicenumber', $this->columnNames('invoiceheader'), true)) {
            $existing = $this->lookupValue('invoiceheader', [
                'hhcinvoicenumber' => $hhcInvoiceNumber,
            ], 'transactionkey');

            if ($existing !== null) {
                return $existing;
            }
        }

        $hhcKey = $row['hhctransactionkey'] ?? null;
        $routeKey = $row['routekey'] ?? null;
        $visitKey = $row['visitkey'] ?? null;

        if ($this->hasMeaningfulIdentity($hhcKey) && in_array('hhctransactionkey', $this->columnNames('invoiceheader'), true)) {
            $existing = $this->lookupValue('invoiceheader', [
                'routekey' => $routeKey,
                'visitkey' => $visitKey,
                'hhctransactionkey' => $hhcKey,
            ], 'transactionkey');

            if ($existing !== null) {
                return $existing;
            }
        }

        return null;
    }

    private function existingArHeaderKey(array $row): mixed
    {
        $invoiceNumber = $row['invoicenumber'] ?? null;
        if ($this->hasMeaningfulIdentity($invoiceNumber)) {
            $existing = $this->lookupValue('arheader', [
                'invoicenumber' => $invoiceNumber,
            ], 'transactionkey');

            if ($existing !== null) {
                return $existing;
            }
        }

        $hhcInvoiceNumber = $row['hhcinvoicenumber'] ?? null;
        if ($this->hasMeaningfulIdentity($hhcInvoiceNumber) && in_array('hhcinvoicenumber', $this->columnNames('arheader'), true)) {
            $existing = $this->lookupValue('arheader', [
                'hhcinvoicenumber' => $hhcInvoiceNumber,
            ], 'transactionkey');

            if ($existing !== null) {
                return $existing;
            }
        }

        $hhcKey = $row['hhctransactionkey'] ?? null;
        $routeKey = $row['routekey'] ?? null;
        $visitKey = $row['visitkey'] ?? null;

        if ($this->hasMeaningfulIdentity($hhcKey) && in_array('hhctransactionkey', $this->columnNames('arheader'), true)) {
            $existing = $this->lookupValue('arheader', [
                'routekey' => $routeKey,
                'visitkey' => $visitKey,
                'hhctransactionkey' => $hhcKey,
            ], 'transactionkey');

            if ($existing !== null) {
                return $existing;
            }
        }

        return null;
    }

    private function salesOrderMapKey(mixed $routeKey, mixed $visitKey, mixed $clientTransactionKey): string
    {
        return implode(':', [
            (string) ($routeKey ?? ''),
            (string) ($visitKey ?? ''),
            (string) ($clientTransactionKey ?? ''),
        ]);
    }

    private function invoiceMapKey(mixed $routeKey, mixed $visitKey, mixed $clientTransactionKey): string
    {
        return implode(':', [
            (string) ($routeKey ?? ''),
            (string) ($visitKey ?? ''),
            (string) ($clientTransactionKey ?? ''),
        ]);
    }

    private function arMapKey(mixed $routeKey, mixed $visitKey, mixed $clientTransactionKey): string
    {
        return implode(':', [
            (string) ($routeKey ?? ''),
            (string) ($visitKey ?? ''),
            (string) ($clientTransactionKey ?? ''),
        ]);
    }

    private function markStartingLoadProcessed(mixed $routeCode, mixed $loadNumber): void
    {
        if (! $this->support->hasTable('startingloaddetail')) {
            return;
        }

        if ($routeCode === null || $routeCode === '' || $loadNumber === null || $loadNumber === '') {
            return;
        }

        $this->support->table('startingloaddetail')
            ->where('routecode', $routeCode)
            ->where('loadperiodnumber', $loadNumber)
            ->update(['status' => 1]);
    }

    private function filterColumns(string $table, array $row): array
    {
        $columns = $this->columnNames($table);
        $payload = array_intersect_key($row, array_flip($columns));

        return $this->sanitizePayload($table, $payload);
    }

    private function columnNames(string $table): array
    {
        return array_map(
            static fn ($column) => $column->Field,
            $this->columnMetadata($table)
        );
    }

    private function columnMetadata(string $table): array
    {
        $resolved = $this->support->resolveTable($table);

        return $this->columnMetaCache[$resolved] ??= DB::select(
            sprintf('SHOW COLUMNS FROM `%s`', str_replace('`', '``', $resolved))
        );
    }

    private function sanitizePayload(string $table, array $payload): array
    {
        $metadata = collect($this->columnMetadata($table))->keyBy('Field');

        foreach ($payload as $field => $value) {
            $column = $metadata->get($field);
            if ($column === null) {
                continue;
            }

            $payload[$field] = $this->sanitizeColumnValue($column, $value);
        }

        return $payload;
    }

    private function sanitizeColumnValue(object $column, mixed $value): mixed
    {
        $type = strtolower((string) ($column->Type ?? ''));
        $nullable = strtoupper((string) ($column->Null ?? '')) === 'YES';
        $default = $column->Default ?? null;

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($this->isDateLikeColumn($type)) {
            if ($value === '' || $value === null || $value === '0') {
                return $nullable ? null : $this->dateColumnFallback($default);
            }

            return $value;
        }

        if ($this->isNumericColumn($type)) {
            if ($value === '' || $value === null) {
                if ($default !== null && strtoupper((string) $default) !== 'NULL') {
                    return $default;
                }

                return $nullable ? null : 0;
            }

            return $value;
        }

        return $value;
    }

    private function isNumericColumn(string $type): bool
    {
        return preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|real|bit)/', $type) === 1;
    }

    private function isDateLikeColumn(string $type): bool
    {
        return preg_match('/^(date|datetime|timestamp|time|year)/', $type) === 1;
    }

    private function dateColumnFallback(mixed $default): ?string
    {
        if ($default === null || strtoupper((string) $default) === 'NULL') {
            return null;
        }

        if (preg_match('/current_timestamp/i', (string) $default) === 1) {
            return now()->toDateTimeString();
        }

        return (string) $default;
    }

    private function decodeItems(mixed $raw, bool $useRaw = false): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $candidates = array_values(array_unique(array_filter([
            $useRaw ? $raw : stripslashes($raw),
            $raw,
            stripslashes($raw),
            $this->repairLegacyJsonString($raw),
            $this->repairLegacyJsonString(stripslashes($raw)),
        ], static fn ($value) => is_string($value) && $value !== '')));

        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function repairLegacyJsonString(string $json): string
    {
        $result = '';
        $length = strlen($json);
        $inString = false;
        $escaping = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $json[$index];

            if (! $inString) {
                if ($char === '"') {
                    $inString = true;
                }

                $result .= $char;
                continue;
            }

            if ($escaping) {
                // Legacy payloads can contain invalid JSON escapes like \7 in comments.
                $result .= '\\';
                if (! in_array($char, ['"', '\\', '/', 'b', 'f', 'n', 'r', 't', 'u'], true)) {
                    $result .= '\\';
                }

                $result .= $char;
                $escaping = false;
                continue;
            }

            if ($char === '\\') {
                $escaping = true;
                continue;
            }

            if ($char === '"') {
                $inString = false;
            }

            $result .= $char;
        }

        if ($escaping) {
            $result .= '\\\\';
        }

        return $result;
    }

    private function normalizeDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? (string) $value : date('Y-m-d', $timestamp);
    }

    private function normalizeDateField(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || $value === '0') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('Y-m-d', $timestamp);
    }

    private function normalizeTimestampField(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || $value === '0') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }
}

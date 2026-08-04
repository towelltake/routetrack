<?php

namespace App\Http\Controllers\Usermanagement;

use App\Http\Controllers\Controller;
use App\Models\ModuleDetail;
use App\Models\ModuleHeader;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\UserType;
use App\Models\UserTypeDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UserPermissionController extends Controller
{
    private const REPORTS_MODULE_ID = 10;
    private const REPORT_FORM_DEFINITIONS = [
        [
            'moduleid' => self::REPORTS_MODULE_ID,
            'formid' => 100001,
            'formname' => 'reports',
            'formdescription' => 'Access reports module',
            'modulename' => 'Reports',
            'order' => 1,
        ],
    ];

    private ?bool $hasViewPermissionColumn = null;
    private ?array $excludedModuleIds = null;
    private ?string $moduleDetailTable = null;
    private ?string $moduleHeaderTable = null;

    private function excludedModuleIds(): array
    {
        if ($this->excludedModuleIds !== null) {
            return $this->excludedModuleIds;
        }

        $this->excludedModuleIds = ModuleHeader::query()
            ->whereIn(DB::raw('LOWER(TRIM(modulename))'), ['operation'])
            ->pluck('moduleid')
            ->map(fn ($value) => (int) $value)
            ->all();

        return $this->excludedModuleIds;
    }

    private function formsQuery()
    {
        return ModuleDetail::query()
            ->whereNotIn(DB::raw('LOWER(TRIM(formname))'), ['tax', 'channel', 'category'])
            ->where(function ($query) {
                $query->where($this->qualifiedModuleDetailColumn('moduleid'), '!=', 5)
                    ->orWhereIn(DB::raw('LOWER(TRIM(' . $this->rawQualifiedModuleDetailColumn('formname') . '))'), ['company group', 'major category', 'sub major category', 'item group', 'items', 'route item group', 'daily salesman load', 'delivery', 'target & commission', 'target group']);
            })
            ->when(!empty($this->excludedModuleIds()), function ($query) {
                $query->whereNotIn($this->qualifiedModuleDetailColumn('moduleid'), $this->excludedModuleIds());
            });
    }

    public function index(): Response
    {
        $modules = ModuleHeader::query()
            ->when(!empty($this->excludedModuleIds()), function ($query) {
                $query->whereNotIn('moduleid', $this->excludedModuleIds());
            })
            ->orderBy('moduleid')
            ->get(['moduleid', 'modulename']);

        $forms = $this->formsCollection();

        return Inertia::render('usermanagement/UserPermission', [
            'modules'   => $modules,
            'forms'     => $forms,
            'users'     => User::orderBy('username')->get(['userid', 'username']),
            'userTypes' => UserType::orderBy('usertypename')->get(['usertypeid', 'usertypename']),
        ]);
    }

    /**
     * Load existing permissions for a given user or user type.
     * GET /usermanagement/user-permission/load?by=1&id=5
     * by=1 => User, by=2 => User Type
     */
    public function load(Request $request): JsonResponse
    {
        $by = (int) $request->query('by', 1);
        $id = (int) $request->query('id', 0);

        if ($id <= 0) {
            return response()->json([]);
        }

        if ($by === 1) {
            $columns = ['formid', 'readdata', 'updatedata', 'insertdata', 'deletedata', 'allpermissions'];
            if ($this->supportsViewPermission('userdetail')) {
                array_splice($columns, 1, 0, 'viewdata');
            }

            $perms = UserDetail::where('userid', $id)->get($columns);
        } else {
            $columns = ['formid', 'readdata', 'updatedata', 'insertdata', 'deletedata', 'allpermissions'];
            if ($this->supportsViewPermission('usertypedetail')) {
                array_splice($columns, 1, 0, 'viewdata');
            }

            $perms = UserTypeDetail::where('usertypeid', $id)->get($columns);
        }

        // Index by formid for easy frontend lookup
        $indexed = [];
        foreach ($perms as $p) {
            $indexed[(int) $p->formid] = [
                'view'   => (bool) ($p->viewdata ?? 0),
                'read'   => (bool) $p->readdata,
                'create' => (bool) $p->insertdata,
                'write'  => (bool) $p->updatedata,
                'delete' => (bool) $p->deletedata,
                'all'    => (bool) $p->allpermissions,
            ];
        }

        return response()->json($indexed);
    }

    /**
     * Save permissions.
     */
    public function save(Request $request): RedirectResponse
    {
        $request->validate([
            'permission_by' => ['required', 'in:1,2'],
            'entity_id'     => ['required', 'integer', 'min:1'],
            'permissions'   => ['present', 'array'],
        ]);

        $by       = (int) $request->permission_by;
        $entityId = (int) $request->entity_id;
        $perms    = $request->permissions; // [formid => [view, read, create, write, delete, all]]

        try {
            DB::transaction(function () use ($by, $entityId, $perms) {
                if ($by === 1) {
                    $this->saveUserPermissions($entityId, $perms);
                } else {
                    $this->saveUserTypePermissions($entityId, $perms);
                }
            });
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Failed to save permissions.');
        }

        return back()->with('success', 'Permissions saved successfully.');
    }

    private function saveUserPermissions(int $userid, array $perms): void
    {
        $forms = $this->formsCollection();
        $hasViewColumn = $this->supportsViewPermission('userdetail');

        // Ensure rows exist for all forms
        $existing = UserDetail::where('userid', $userid)->pluck('formid')->map(fn ($v) => (int) $v)->toArray();

        $toInsert = [];
        foreach ($forms as $form) {
            if (!in_array((int) $form->formid, $existing)) {
                $row = [
                    'username'       => '',
                    'formname'       => $form->formname,
                    'formdescription'=> $form->formdescription,
                    'readdata'       => 0,
                    'updatedata'     => 0,
                    'insertdata'     => 0,
                    'deletedata'     => 0,
                    'allpermissions' => 0,
                    'userid'         => $userid,
                    'moduleid'       => $form->moduleid,
                    'formid'         => $form->formid,
                ];

                if ($hasViewColumn) {
                    $row['viewdata'] = 0;
                }

                $toInsert[] = $row;
            }
        }

        if (!empty($toInsert)) {
            UserDetail::insert($toInsert);
        }

        // Update permissions per form
        foreach ($forms as $form) {
            $fid  = (int) $form->formid;
            $p    = $perms[$fid] ?? [];
            $view = empty($p['view']) ? 0 : 1;
            $read = empty($p['read']) ? 0 : 1;
            $create = empty($p['create']) ? 0 : 1;
            $write = empty($p['write']) ? 0 : 1;
            $del = empty($p['delete']) ? 0 : 1;
            $all = empty($p['all']) ? 0 : 1;

            $update = [
                'readdata'       => $read,
                'updatedata'     => $write,
                'insertdata'     => $create,
                'deletedata'     => $del,
                'allpermissions' => $all,
            ];

            if ($hasViewColumn) {
                $update['viewdata'] = $view;
            }

            DB::table('userdetail')
                ->where('userid', $userid)
                ->where('formid', $fid)
                ->update($update);
        }
    }

    private function saveUserTypePermissions(int $usertypeid, array $perms): void
    {
        $forms = $this->formsCollection();
        $hasViewColumn = $this->supportsViewPermission('usertypedetail');

        $existing = UserTypeDetail::where('usertypeid', $usertypeid)->pluck('formid')->map(fn ($v) => (int) $v)->toArray();

        $toInsert = [];
        foreach ($forms as $form) {
            if (!in_array((int) $form->formid, $existing)) {
                $row = [
                    'usertypeid'     => $usertypeid,
                    'formname'       => $form->formname,
                    'formdescription'=> $form->formdescription,
                    'readdata'       => 0,
                    'updatedata'     => 0,
                    'insertdata'     => 0,
                    'deletedata'     => 0,
                    'allpermissions' => 0,
                    'moduleid'       => $form->moduleid,
                    'formid'         => $form->formid,
                ];

                if ($hasViewColumn) {
                    $row['viewdata'] = 0;
                }

                $toInsert[] = $row;
            }
        }

        if (!empty($toInsert)) {
            UserTypeDetail::insert($toInsert);
        }

        foreach ($forms as $form) {
            $fid  = (int) $form->formid;
            $p    = $perms[$fid] ?? [];
            $view = empty($p['view']) ? 0 : 1;
            $read = empty($p['read']) ? 0 : 1;
            $create = empty($p['create']) ? 0 : 1;
            $write = empty($p['write']) ? 0 : 1;
            $del = empty($p['delete']) ? 0 : 1;
            $all = empty($p['all']) ? 0 : 1;

            $update = [
                'readdata'       => $read,
                'updatedata'     => $write,
                'insertdata'     => $create,
                'deletedata'     => $del,
                'allpermissions' => $all,
            ];

            if ($hasViewColumn) {
                $update['viewdata'] = $view;
            }

            DB::table('usertypedetail')
                ->where('usertypeid', $usertypeid)
                ->where('formid', $fid)
                ->update($update);
        }
    }

    private function supportsViewPermission(string $table): bool
    {
        return Schema::hasColumn($table, 'viewdata');
    }

    private function formsCollection(): Collection
    {
        $dbForms = $this->formsQuery()
            ->leftJoin($this->moduleHeaderTable(), $this->qualifiedModuleHeaderColumn('moduleid'), '=', $this->qualifiedModuleDetailColumn('moduleid'))
            ->orderBy($this->qualifiedModuleHeaderColumn('moduleid'))
            ->orderBy($this->qualifiedModuleDetailColumn('order'))
            ->orderByRaw("
                CASE
                    WHEN LOWER(TRIM(" . $this->rawQualifiedModuleDetailColumn('formname') . ")) = 'account customer category' THEN 0
                    WHEN LOWER(TRIM(" . $this->rawQualifiedModuleDetailColumn('formname') . ")) = 'account customer' THEN 1
                    ELSE 2
                END
            ")
            ->orderBy($this->qualifiedModuleDetailColumn('formdescription'))
            ->get([
                $this->qualifiedModuleDetailColumn('moduleid'),
                $this->qualifiedModuleDetailColumn('formid'),
                $this->qualifiedModuleDetailColumn('formname'),
                $this->qualifiedModuleDetailColumn('formdescription'),
                $this->qualifiedModuleDetailColumn('order'),
                $this->qualifiedModuleHeaderColumn('modulename'),
            ]);

        return $dbForms
            ->concat(collect(self::REPORT_FORM_DEFINITIONS)->map(fn (array $form) => (object) $form))
            ->sortBy([
                ['moduleid', 'asc'],
                ['order', 'asc'],
                ['formdescription', 'asc'],
            ])
            ->values();
    }

    private function moduleDetailTable(): string
    {
        return $this->moduleDetailTable ??= (new ModuleDetail())->getTable();
    }

    private function moduleHeaderTable(): string
    {
        return $this->moduleHeaderTable ??= (new ModuleHeader())->getTable();
    }

    private function qualifiedModuleDetailColumn(string $column): string
    {
        return $this->moduleDetailTable() . '.' . $column;
    }

    private function qualifiedModuleHeaderColumn(string $column): string
    {
        return $this->moduleHeaderTable() . '.' . $column;
    }

    private function rawQualifiedModuleDetailColumn(string $column): string
    {
        return DB::getTablePrefix() . $this->moduleDetailTable() . '.' . $column;
    }
}

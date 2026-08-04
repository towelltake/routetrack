<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserTypeDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserPermissionSync
{
    public static function syncForUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            DB::table('userdetail')->where('userid', $user->userid)->delete();

            if (empty($user->usertypeid)) {
                return;
            }

            $hasViewColumn = Schema::hasColumn('userdetail', 'viewdata')
                && Schema::hasColumn('usertypedetail', 'viewdata');

            $typePermissions = UserTypeDetail::query()
                ->where('usertypeid', $user->usertypeid)
                ->get(array_values(array_filter([
                    'formname',
                    'formdescription',
                    $hasViewColumn ? 'viewdata' : null,
                    'readdata',
                    'updatedata',
                    'insertdata',
                    'deletedata',
                    'allpermissions',
                    'moduleid',
                    'formid',
                ])));

            if ($typePermissions->isEmpty()) {
                return;
            }

            DB::table('userdetail')->insert(
                $typePermissions->map(function ($permission) use ($user, $hasViewColumn) {
                    $row = [
                        'username' => $user->username,
                        'formname' => $permission->formname,
                        'formdescription' => $permission->formdescription,
                        'readdata' => (int) $permission->readdata,
                        'updatedata' => (int) $permission->updatedata,
                        'insertdata' => (int) $permission->insertdata,
                        'deletedata' => (int) $permission->deletedata,
                        'allpermissions' => (int) $permission->allpermissions,
                        'userid' => $user->userid,
                        'moduleid' => $permission->moduleid,
                        'formid' => $permission->formid,
                    ];

                    if ($hasViewColumn) {
                        $row['viewdata'] = (int) ($permission->viewdata ?? 0);
                    }

                    return $row;
                })->all()
            );
        });
    }

    public static function syncUsername(User $user): void
    {
        DB::table('userdetail')
            ->where('userid', $user->userid)
            ->update(['username' => $user->username]);
    }

    public static function deleteForUser(User $user): void
    {
        DB::table('userdetail')->where('userid', $user->userid)->delete();
    }
}

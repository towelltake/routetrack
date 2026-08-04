<?php

namespace Database\Seeders;

use App\Models\ModuleDetail;
use App\Models\User;
use App\Models\UserType;
use App\Support\UserPermissionSync;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $adminType = UserType::query()->firstOrCreate(
                ['usertypeid' => 1],
                ['usertypename' => 'Administrator']
            );

            if ($adminType->usertypename !== 'Administrator') {
                $adminType->usertypename = 'Administrator';
                $adminType->save();
            }

            $this->seedAdministratorPermissions((int) $adminType->usertypeid);

            $username = (string) env('BOOTSTRAP_ADMIN_USERNAME', 'admin');
            $password = (string) env('BOOTSTRAP_ADMIN_PASSWORD', 'admin1234');

            $user = User::query()->firstOrNew(['username' => $username]);
            $user->usertypeid = (int) $adminType->usertypeid;
            $user->accesstypeid = null;

            if (!$user->exists || !Hash::check($password, (string) $user->password)) {
                $user->password = Hash::make($password);
            }

            $user->save();

            UserPermissionSync::syncForUser($user);
        });
    }

    private function seedAdministratorPermissions(int $userTypeId): void
    {
        $hasViewColumn = Schema::hasColumn('usertypedetail', 'viewdata');

        foreach (ModuleDetail::query()->orderBy('moduleid')->orderBy('order')->get() as $form) {
            $row = [
                'usertypeid' => $userTypeId,
                'formname' => $form->formname,
                'formdescription' => $form->formdescription,
                'readdata' => 1,
                'updatedata' => 1,
                'insertdata' => 1,
                'deletedata' => 1,
                'allpermissions' => 1,
                'moduleid' => $form->moduleid,
                'formid' => $form->formid,
            ];

            if ($hasViewColumn) {
                $row['viewdata'] = 1;
            }

            DB::table('usertypedetail')->updateOrInsert(
                [
                    'usertypeid' => $userTypeId,
                    'formid' => $form->formid,
                ],
                $row
            );
        }
    }
}

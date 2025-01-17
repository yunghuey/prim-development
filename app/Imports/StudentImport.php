<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\Parents;
use FontLib\Table\Type\name;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Models\OrganizationRole;
use App\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StudentImport implements ToModel, WithValidation, WithHeadingRow
{
    public function __construct($class_id)
    {
        $id = DB::table('class_organization')->where('class_id', $class_id)->first();
        $this->class_id = $id;
    }

    public function rules(): array
    {
        return [
            'no_kp' => [
                'required',
                 Rule::unique('students', 'icno')
            ],
        ];
    }

    public function customValidationMessages()
    {
        return [
            'no_kp.unique' => 'Terdapat maklumat murid yang telah wujud',
            'no_kp.required' => 'Maklumat murid diperlukan',
        ];
    }

    public function model(array $row)
    {
        if(!isset($row['nama']) || !isset($row['no_kp']) || !isset($row['email'])){
            throw ValidationException::withMessages(["error" => "Invalid headers or missing column"]);
        }

        if(!isset($row['nama_penjaga']) || !isset($row['no_kp']) || !isset($row['no_tel_bimbit_penjaga'])){
            throw ValidationException::withMessages(["error" => "Invalid headers or missing column"]);
        }

        $phone = trim((string)$row['no_tel_bimbit_penjaga']);

        if(!$this->startsWith($phone,"+60") && !$this->startsWith($phone,"60")){
            if(strlen($phone) == 10) {
                $phone = str_pad($phone, 12, "+60", STR_PAD_LEFT);
            } 
            elseif(strlen($phone) == 11)
            {
                $phone = str_pad($phone, 13, "+60", STR_PAD_LEFT);
            }   
        } else if($this->startsWith($phone,"60")){

            if(strlen($phone) == 11) {
                $phone = str_pad($phone, 12, "+60", STR_PAD_LEFT);
            } 
            elseif(strlen($phone) == 12)
            {
                $phone = str_pad($phone, 13, "+60", STR_PAD_LEFT);
            } 
        }
        elseif($this->startsWith($phone,"+60")) {
            // do nothing
        }
        else{
            throw ValidationException::withMessages(["error" => "Invalid phone number"]);
        }

        $co = DB::table('class_organization')
            ->select('id', 'organization_id as oid')
            ->where('class_id', $this->class_id->class_id)
            ->first();

        $gender = (int) substr($row["no_kp"], -1) % 2 == 0 ? "P" : "L";
        
        $student = new Student([
            'nama' => $row["nama"],
            'icno' => $row["no_kp"],
            'gender' => $gender,
            'email' => $row["email"]
        ]);

        $student->save();
        // id kelas
        DB::table('class_student')->insert([
            'organclass_id'   => $co->id,
            'student_id'      => $student->id,
            'start_date'      => now(),
            'status'          => 1,
        ]);

        $parent = DB::table('users')
            ->select()
            ->where('telno', $row['no_tel_bimbit_penjaga'])
            ->first();
        
        if(is_null($parent))
        {
            $parent = new Parents([
                'name'           =>  strtoupper($row['nama_penjaga']),
                // 'icno'           =>  $row['no_kp_penjaga'],
                'email'          =>  isset($row['email_penjaga']) ? $row['email_penjaga'] : NULL,
                'password'       =>  Hash::make('abc123'),
                'telno'          =>  $phone,
                'remember_token' =>  Str::random(40),
            ]);
            $parent->save();
        }

        DB::table('organization_user')->insert([
            'organization_id'   => $co->oid,
            'user_id'           => $parent->id,
            'role_id'           => 6,
            'start_date'        => now(),
            'status'            => 1,
        ]);

        $ou = DB::table('organization_user')
                    ->where('user_id', $parent->id)
                    ->where('organization_id', $co->oid)
                    ->where('role_id', 6)
                    ->first();

        $user = User::find($parent->id);
        // role parent
        $rolename = OrganizationRole::find(6);
        $user->assignRole($rolename->nama);

        DB::table('organization_user_student')
                ->insert([
                    'organization_user_id'  => $ou->id,
                    'student_id'            => $student->id
                ]);
            
        DB::table('students')
            ->where('id', $student->id)
            ->update(['parent_tel' => $parent->telno]);
    }

    public function startsWith($string, $startString) {
        $len = strlen($startString);
        return (substr($string, 0, $len) === $startString);
    }
}

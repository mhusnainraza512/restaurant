<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Temporary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CustomerController extends Controller
{


    // public function checkLogin() {
    //     // return auth()->check() ? 1 : 0;
    //     // return Auth::user() ? 1 : 0;
    //     // if (Auth::check()) {
    //     //    return 1;
    //     // } else {
    //     //     return 0;
    //     // }
    //     return 0;
    // }

     //get all customer
    public function index()
    {
        $user = Auth::user();
        if ($user->branch_id == null) {
            $customers = Customer::all();
        } else {
            $customers = Customer::where('branch_id', $user->branch_id)->get();
        }
        $modifiedCustomer = array();
        foreach ($customers as $customer) {
            $temp = new Temporary;
            $temp->id = $customer->id;
            $temp->name = $customer->name;
            $temp->email = $customer->email;
            $temp->phn_no = $customer->phn_no;
            $temp->address = $customer->address;
            $temp->branch_id = $customer->branch_id;
            $temp->zipcode = $customer->zipcode;
            $branch = Branch::where('id', $customer->branch_id)->first();
            if (!is_null($branch)) {
                $temp->branch_name = $branch->name;
            } else {
                $temp->branch_name = null;
            }
            $temp->slug = $customer->slug;
            array_push($modifiedCustomer, $temp);
        }
        return [customPaginate($modifiedCustomer), $modifiedCustomer];
    }

    //get all customer online
    public function indexOnline()
    {
        $user = Auth::user();
        if ($user->branch_id == null) {
            $customers = User::where('user_type', 'customer')->get()->toArray();
        } else {
            $customers =  User::where('user_type', 'customer')->where('branch_id', $user->branch_id)->get()->toArray();
        }
        return [customPaginate($customers), $customers];
    }

    //save new Customer
    public function store(Request $request)
    {
        $request->validate(
            [
            'phn_no'   => ['unique:customers']
        ],
            [
                'phn_no.unique'                => 'A customer exists with this phone number',
            ]
        );
        $customer = new Customer;
        $customer->name = $request->name;
        $customer->email = Str::lower($request->email);
        $customer->phn_no = $request->phn_no;
        $customer->address = $request->address;
        $customer->branch_id = $request->branch_id;
        $customer->slug =  Str::random(3).'-'.time().'-'.Str::slug($request->name);
        $customer->save();

        //get all customers
        return $this->index();
    }

    //save new Customer by csv file
    public function upload_csv(Request $request)
    {
        if ($request->hasFile('csv_file')) {
            $file = $request->file('csv_file');
            $path = $file->getRealPath();

            // Read the CSV file
            $data = array_map('str_getcsv', file($path));

            // Remove the header row if necessary
            $header = array_shift($data);

            // Loop through the data and store it in the database
            foreach ($data as $row) {
                $slug = Str::random(3).'-'.time().'-'.Str::slug($row[0]);
                $customer = Customer::where('email', $row[1])
                ->orWhere('phn_no', $row[2])
                ->orWhere('slug', $slug)
                ->first();

                if(empty($customer)){
                    $customer = new Customer;
                    $customer->name = $row[0]; //name
                    $customer->email = $row[1] != '' ? Str::lower($row[1]) : null; //email
                    $customer->phn_no = $row[2]; //phn_no
                    $customer->address = $row[3]; //address
                    $customer->zipcode = $row[4]; //zipcode_id
                    $customer->branch_id = $row[5]; //branch_id
                    $customer->slug = $slug; //slug
                    $customer->save();
                }
            }

            // Redirect or return a response indicating success
            return response()->json([
                'success' => true,
                'message' => 'CSV file was uploaded successfully!'
            ]);
        }

        // Redirect or return a response indicating failure
        return response()->json([
            'success' => true,
            'message' => 'CSV file was not uploaded!'
        ]);
    }

    //update customer
    public function update(Request $request)
    {
        $customer = Customer::where('slug', $request->editSlug)->first();
        if ($request->phn_no != $customer->phn_no) {
            $request->validate(
                [
                'phn_no' => ['unique:customers']
            ],
                [
                    'phn_no.unique' => 'A customer exists with this phone number',
                ]
            );
        }

        if ($request->name !== "null") {
            $customer->name = $request->name;
        }

        if ($request->email !== "null") {
            $customer->email = Str::lower($request->email);
        }

        if ($request->phn_no !== "null") {
            $customer->phn_no = $request->phn_no;
        }

        if ($request->address !== "null") {
            $customer->address = $request->address;
        }

        if ($request->branch_id) {
            $customer->branch_id = $request->branch_id;
        }

        $customer->slug =  Str::random(3).'-'.time().'-'.Str::slug($request->name);
        $customer->save();

        //get all customers
        return $this->index();
    }

    //delete customer
    public function destroy($slug)
    {
        $customer = Customer::where('slug', $slug)->first();
        $customer->delete();
        //get all customers
        return $this->index();
    }
}
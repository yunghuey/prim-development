<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class LandingPageController extends Controller
{
    private $organization;

    public function __construct(Organization $organization)
    {
        $this->organization = $organization;
    }

    public function index()
    {
        // return view('landing-page.index');
        return view('custom-errors.500');
    }

    public function organizationList()
    {
        return view('landing-page.organization_list');
    }

    public function activitylist()
    {
        return view('landing-page.listactivity');
    }

    public function activitydetails()
    {
        return view('landing-page.activitydetails');
    }

    public function getDonationDatatable()
    {
        $data = DB::table('donations')
            ->join('donation_organization', 'donation_organization.donation_id', '=', 'donations.id')
            ->join('organizations', 'organizations.id', '=', 'donation_organization.organization_id')
            ->select('donations.id', 'donations.nama as nama_derma', 'donations.description', 'donations.date_started', 'donations.date_end', 'donations.status', 'donations.url', 'organizations.nama as nama_organisasi', 'organizations.email', 'organizations.address')
            ->where('donations.status', 1)
            ->orderBy('donations.nama')
            ->get();

        $table = Datatables::of($data);

        $table->addColumn('action', function ($row) {
            $btn = '<div class="d-flex justify-content-center">';
            $btn = $btn . '<a href="sumbangan/' . $row->url . ' " class="btn btn-success m-1">Bayar</a></div>';
            return $btn;
        });
        $table->rawColumns(['action']);
        return $table->make(true);
    }

    // ********************************Landing page Donation**********************************

    public function indexDonation()
    {
        $organization = Organization::all()->count();
        $transactions = Transaction::where('nama', 'LIKE', 'Donation%')
            ->where('status', 'Success')
            ->get()->count();
        $donation = Donation::all()->count();

        // dd($transactions->count());
        return view('landing-page.donation.index', compact('organization', 'transactions', 'donation'));
    }

    public function organizationListDonation()
    {
        return view('landing-page.donation.organization_list');
    }

    public function activitylistDonation()
    {
        return view('landing-page.donation.listactivity');
    }

    public function activitydetailsDonation()
    {
        return view('landing-page.donation.activitydetails');
    }

    public function getOrganizationByType($type)
    {
        try {
            $organizations = $this->organization->getOrganizationByType($type);
            return $organizations;
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage(), 500);
        }
    }

    public function getDonationDatatableDonation(Request $request)
    {
        $data = $this->getOrganizationByType($request->type);
        
        $table = Datatables::of($data);

        $table->addColumn('action', function ($row) {
            $btn = '<div class="d-flex justify-content-center">';
            $btn = $btn . '<a href="sumbangan/' . $row->url . ' " class="btn btn-success m-1">Bayar</a></div>';
            return $btn;
        });
        $table->rawColumns(['action']);
        return $table->make(true);
    }
}

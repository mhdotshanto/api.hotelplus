<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccBankInsertRequest;
use App\Models\AccBank;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AccBankController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return response()->json(
            AccBank::orderBy('created_at', 'ASC')
                ->paginate($request->get('per_page'))
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AccBankInsertRequest $request)
    {
        try {
            // Get validated data from the request
            $validated = $request->validated();

            // Prepare data for insert, including enum mapping if needed
            $data = [
                'name'           => $validated['name'],
                'short_name'     => $validated['short_name'],
                'slug'           => Str::slug($validated['name']), // generate slug from name
                'banking_type'   => $validated['banking_type'],
                'gateway_fee'    => $validated['gateway_fee'],
                'contact_person' => $validated['contact_person'],
                'designation'    => $validated['designation'],
                'mobile'         => $validated['mobile'],
                'email'          => $validated['email'],
            ];

            // Conditionally add fields based on banking_type
            if ($validated['banking_type'] === 'mfs') {
                $data['banking_type'] = $validated['mfs_provider']; // override banking_type with mfs_provider value
                $data['account_number'] = $validated['mfs_number']; // store mfs number as account_number for consistency
                // branch_name and account_name are not applicable here
                $data['branch_name'] = null;
                $data['account_name'] = null;
            } else {
                // For regular banking type
                $data['branch_name'] = $validated['branch_name'] ?? null;
                $data['account_number'] = $validated['account_number'] ?? null;
                $data['account_name'] = $validated['account_name'] ?? null;
            }

            // Create the bank record
            AccBank::create($data);

            // Return success JSON response with created data
            return response()->json([
                'success' => true,
                'message' => 'Bank created successfully',
            ], 201);
        } catch (\Exception $e) {
            // Return JSON error response
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(AccBank $accBank)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AccBank $accBank)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AccBankInsertRequest $request, AccBank $accBank)
    {
        try {
            // Get validated data from request
            $validated = $request->validated();

            // Prepare data for update
            $data = [
                'name'           => $validated['name'],
                'short_name'     => $validated['short_name'],
                'slug'           => Str::slug($validated['name']),
                'banking_type'   => $validated['banking_type'],
                'gateway_fee'    => $validated['gateway_fee'],
                'contact_person' => $validated['contact_person'],
                'designation'    => $validated['designation'],
                'mobile'         => $validated['mobile'],
                'email'          => $validated['email'],
            ];

            // Conditional fields based on banking_type
            if ($validated['banking_type'] === 'mfs') {
                $data['banking_type'] = $validated['mfs_provider'];
                $data['account_number'] = $validated['mfs_number'];
                $data['branch_name'] = null;
                $data['account_name'] = null;
            } else {
                $data['branch_name'] = $validated['branch_name'] ?? null;
                $data['account_number'] = $validated['account_number'] ?? null;
                $data['account_name'] = $validated['account_name'] ?? null;
            }

            // Update the model
            $accBank->update($data);

            // Return success JSON response
            return response()->json([
                'success' => true,
                'message' => 'Bank updated successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bank' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AccBank $accBank)
    {
        try {
            $accBank->delete();
            return response()->json(['success' => true, 'message' => 'Bank deleted successfully',], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bank' . $e->getMessage(),
            ], 500);
        }
    }

}

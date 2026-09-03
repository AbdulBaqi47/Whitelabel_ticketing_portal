<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class MarginManagerController extends Controller
{

    public function getByMarginId(Request $request)
    {

        $request->validate([
            'margin_id' => 'required|exists:airline_margins,uuid',
        ]);

        $branches = \App\Models\Organization::whereHas('main_user.roles', function ($subQuery) {
            $subQuery->where('name', 'branch');
        })->where('status', true)->get();

        $default_margin = \App\Models\AirlineMargin::where('uuid', $request->margin_id)->first();
        $branchData = [];


        foreach ($branches as $branch) {

            $margin_manager = \App\Models\MarignOrigination::where(['org_id' => $branch->id, 'airline_margin_id' => $default_margin->id])->first();
            $margin = [];
            if ($margin_manager) {
                $margin['margin'] = $margin_manager['margin'];
                $margin['margin_type'] = $margin_manager['margin_type'];
                $margin['assigned'] = true;
            } else {
                $margin['margin'] = $default_margin['margin'];
                $margin['margin_type'] = $default_margin['margin_type'];
                $margin['assigned'] = false;
            }

            $margin['branch_id'] = $branch->id;
            $margin['branch_name'] = $branch->name;
            $margin['apply_to_all'] = $margin_manager?->apply_all;
            $margin['apply_all_value'] = is_null($margin_manager?->apply_value) ? $default_margin['margin'] : $margin_manager?->apply_value;
            $branchData[] = $margin;
        }

        return Response::successResponse(200, 'Assigned Branch Margin List', $branchData);
    }

    public function assignToBranch(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'margin_id' => 'required|exists:airline_margins,uuid',
            'branchData' => 'required|array|min:1',
            'branchData.*.branch_id' => 'required|integer',
            'branchData.*.margin' => 'required|numeric',
            'branchData.*.margin_type' => 'required|in:amount,percentage',
            'branchData.*.assigned' => 'required|boolean',
            'branchData.*.apply_to_all' => 'required|boolean',
        ]);
    
        foreach ($request->input('branchData', []) as $index => $item) {
            $validator->addRules([
                "branchData.$index.apply_all_value" => ($item['apply_to_all'] ?? false) ? 'required|string' : 'nullable|string',
            ]);
        }
    
        $validator->validate();
        $reqData = $request->all();

        try {

            $margin = \App\Models\AirlineMargin::where('uuid', $reqData['margin_id'])->firstOrFail();

            foreach ($reqData['branchData'] as $key => $data) {

                $branchId = $data['branch_id'];
                $assigned = $data['assigned'];
                $marginValue = $data['margin'];
                $marginType = $data['margin_type'];
                $applyToAll = $data['apply_to_all'];
                $applyAllValue = $data['apply_all_value'] ?? null;
            
                $marOrg = \App\Models\MarignOrigination::where([
                    'airline_margin_id' => $margin->id,
                    'org_id' => $branchId
                ])->first();

                if (!$assigned) {
                    
    
                    if ($marOrg) {
                        \App\Models\MarignOrigination::where([
                            'parent_org_id' => $branchId,
                            'airline_margin_id' => $margin->id
                        ])->delete();
    
                        $marOrg->delete();
                    }
    
                    continue;
                }

                if(!$applyToAll){
                    if($marOrg){
                        $marOrg->update(['apply_all'=> false, 'apply_value'=>null]);

                        \App\Models\MarignOrigination::where([
                            'parent_org_id' => $branchId,
                            'airline_margin_id' => $margin->id
                        ])->delete();
                    }
                }

                $margin_origination_data = [
                    'margin' => $marginValue,
                    'margin_type' => $marginType,
                ];
                
                if ($assigned && $applyToAll) {
                    $margin_origination_data['apply_all'] = true;
                    $margin_origination_data['apply_value'] = $applyAllValue;
                }
                
                \App\Models\MarignOrigination::updateOrCreate(
                    [
                        'airline_margin_id' => $margin->id,
                        'org_id' => $branchId,
                    ],
                    $margin_origination_data
                );
                

                if ($assigned && $applyToAll) {
                    $childOrgs = \App\Models\Organization::where('parent_id', $branchId)->get();
    
                    foreach ($childOrgs as $child) {
                        \App\Models\MarignOrigination::updateOrCreate(
                            [
                                'airline_margin_id' => $margin->id,
                                'org_id' => $child->id,
                            ],
                            [
                                'margin' => $applyAllValue ?: $marginValue,
                                'margin_type' => $marginType,
                                'parent_org_id' => $branchId,
                            ]
                        );
                    }
    
                } else {
                    \App\Models\MarignOrigination::where([
                        'airline_margin_id' => $margin->id,
                    ])->whereIn('org_id', function ($query) use ($branchId) {
                        $query->select('id')
                            ->from('organizations')
                            ->where('parent_id', $branchId);
                    })->delete();
                }

            }
    
            return Response::successResponse(200, 'Successfully Assigned Margin');
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function assignToBranchByAirline(Request $request)
    {
        $reqData = $request->validate([
            'branch_id' => 'required|integer|exists:organizations,id',
            'branchData' => 'required|array|min:1',
            'branchData.*.margin_id' => 'required|integer|exists:airline_margins,id',
            'branchData.*.margin' => 'required|numeric',
            'branchData.*.margin_type' => 'required|in:amount,percentage',
            'branchData.*.assigned' => 'required|boolean',
            'branchData.*.apply_to_all' => 'required|boolean',
            'branchData.*.apply_all_value' => 'required_if:branchData.*.apply_to_all,true|nullable|string',
        ]);

        try {
            $branchId = $reqData['branch_id'];

            foreach ($reqData['branchData'] as $key => $data) {

                $marginId = $data['margin_id'];
                $assigned = $data['assigned'];
                $applyToAll = $data['apply_to_all'];
                $applyAllValue = $data['apply_all_value'] ?? null;
                $marginValue = $data['margin'];
                $marginType = $data['margin_type'];

                $marOrg = \App\Models\MarignOrigination::where([
                    'airline_margin_id' => $marginId,
                    'org_id' => $branchId
                ])->first();

                if (!$assigned) {

                    if ($marOrg) {
                        \App\Models\MarignOrigination::where([
                            'parent_org_id' => $branchId,
                            'airline_margin_id' => $marginId
                        ])->delete();
                        $marOrg->delete();
                    }

                    continue;
                }

                if(!$applyToAll){
                    if($marOrg){
                        $marOrg->update(['apply_all'=> false, 'apply_value'=>null]);

                        \App\Models\MarignOrigination::where([
                            'parent_org_id' => $branchId,
                            'airline_margin_id' => $marginId
                        ])->delete();
                    }
                }

                $margin_origination_data = [
                    'margin' => $marginValue,
                    'margin_type' => $marginType,
                ];
                
                if ($assigned && $applyToAll) {
                    $margin_origination_data['apply_all'] = true;
                    $margin_origination_data['apply_value'] = $applyAllValue;
                }
                
                \App\Models\MarignOrigination::updateOrCreate(
                    [
                        'airline_margin_id' => $marginId,
                        'org_id' => $branchId,
                    ],
                    $margin_origination_data
                );

                $childOrgs = \App\Models\Organization::where('parent_id', $branchId)->get();

                if ($assigned && $applyToAll) {
                    foreach ($childOrgs as $child) {
                        \App\Models\MarignOrigination::updateOrCreate(
                            [
                                'airline_margin_id' => $marginId,
                                'org_id' => $child->id,
                            ],
                            [
                                'margin' => $applyAllValue ?? $marginValue,
                                'margin_type' => $marginType,
                                'parent_org_id' => $branchId,
                            ]
                        );
                    }
                } else {
                    \App\Models\MarignOrigination::where([
                        'airline_margin_id' => $marginId,
                    ])->whereIn('org_id', $childOrgs->pluck('id'))->delete();
                }

                \App\Models\AirlineMargin::where('id', $marginId)->update([
                    'apply_to_all' => $applyToAll,
                    'apply_all_value'=>$applyAllValue
                ]);
            }

            return Response::successResponse(200, 'Successfully Assigned Margins');
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function branchMargins(Request $request)
    {

        $reqData = $request->validate([
            'branch_id' => 'required',
        ]);

        try {

            $airline_margins = \App\Models\AirlineMargin::with('airline')->where('status', true)->get();

            $branch_margin_data = [];
            foreach ($airline_margins as $airline_margin) {
                $margin = [];

                $margin_manager = \App\Models\MarignOrigination::where(['org_id' => $reqData['branch_id'], 'airline_margin_id' => $airline_margin->id])->first();

                if (!is_null($margin_manager)) {
                    $margin['margin'] = $margin_manager['margin'];
                    $margin['margin_type'] = $margin_manager['margin_type'];
                    $margin['assigned'] = true;
                } else {
                    $margin['margin'] = $airline_margin['margin'];
                    $margin['margin_type'] = $airline_margin['margin_type'];
                    $margin['assigned'] = false;
                }

                $margin['margin_name'] = $airline_margin->airline->name;
                $margin['margin_id'] = $airline_margin->id;
                $margin['apply_to_all'] = $airline_margin['apply_to_all'];
                $margin['apply_to_all'] = $margin_manager?->apply_all;
                $margin['apply_all_value'] = is_null($margin_manager?->apply_value) ? $airline_margin?->margin : $margin_manager?->apply_value;
                $branch_margin_data[] = $margin;
            }

            return Response::successResponse(200, 'Assigned Branch Margin List', $branch_margin_data);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function agencyMarginAssign(Request $request, string $uuid)
    {

        $reqData = $request->validate([
            'agencyData' => 'required|array|min:1',

            'agencyData.*.margin_id' => 'required',
            'agencyData.*.margin' => 'required|numeric',
            'agencyData.*.margin_type' => 'required|in:amount,percentage',
            'agencyData.*.assigned' => 'required|boolean',
        ]);

        $org = \App\Models\Organization::where('uuid', $uuid)->first();
        foreach ($reqData['agencyData'] as $data) {

            if (!$data['assigned']) {

                $mar_org = \App\Models\MarignOrigination::where(['airline_margin_id' => $data['margin_id'], 'org_id' => $org->id, 'parent_org_id' => $org->parent_id])->first();

                if (!is_null($mar_org)) {
                    $mar_org->delete();
                }

                continue;
            }

            \App\Models\MarignOrigination::updateOrCreate(
                [
                    'airline_margin_id' => $data['margin_id'],
                    'org_id' => $org->id,
                    'parent_org_id' => $org->parent_id
                ],
                [
                    'margin' => $data['margin'],
                    'margin_type' => $data['margin_type'],
                    'parent_org_id' => $org->parent_id,
                ]
            );
        }

        return Response::successResponse(200, 'Successfully Assigned Margins');
    }


    public function agenciesMargins(string $uuid)
    {
        $org = \App\Models\Organization::where('uuid', $uuid)->first();

        if ($org && !is_null($org->parent_id)) {

            $margin_managers = \App\Models\MarignOrigination::with('airline_margin.airline:id,name')->whereHas('airline_margin', function ($q){
                $q->where('status', true);
            })->where(['org_id' => $org->parent_id])->get();
            
            $agent_margin_data = [];
            foreach ($margin_managers as $default_margin_manager) {
                $margin_manager = \App\Models\MarignOrigination::where(['org_id' => $org->id, 'airline_margin_id' => $default_margin_manager->airline_margin_id])->first();

                $margin = [];

                if (!is_null($margin_manager)) {
                    $margin['branch_margin'] = $default_margin_manager['margin'];
                    $margin['branch_margin_type'] = $default_margin_manager['margin_type'];
                    $margin['margin'] = $margin_manager['margin'];
                    $margin['margin_type'] = $margin_manager['margin_type'];
                    $margin['assigned'] = true;
                } else {
                    $margin['branch_margin'] = $default_margin_manager['margin'];
                    $margin['branch_margin_type'] = $default_margin_manager['margin_type'];
                    $margin['margin'] = $default_margin_manager['margin'];
                    $margin['margin_type'] = $default_margin_manager['margin_type'];
                    $margin['assigned'] = false;
                }

                $margin['margin_name'] = $default_margin_manager->airline_margin->airline->name;
                $margin['margin_id'] = $default_margin_manager->airline_margin_id;

                $agent_margin_data[] = $margin;
            }
            return Response::successResponse(200, 'Agency Margin List', $agent_margin_data);
        } else {
            return Response::errorResponse(500, 'Record Not Found!');
        }
    }

    public function agencyAirlineMarginList()
    {

        $user = \Illuminate\Support\Facades\Auth::user();
        $role_name = $user->roles[0]->name;

        if (!in_array($role_name, ['a-employee', 'agency', 'sub-agent', 'branch', 'b-employee'])) {
            return Response::errorResponse(400, "You don't have permission to access this module");
        }

        try {

            $airline_margins = \App\Models\AirlineMargin::with([
                'airline:id,name',
                'margin_originations' => function ($q) use ($user) {
                    $q->where('org_id', $user->org_id);
                }
            ])
                ->where('status', true)
                ->get()
                ->filter(fn($item) => $item->margin_originations->isNotEmpty())
                ->map(function ($item) {
                    $origin = $item->margin_originations->first();
                    $item->margin = $origin->margin;
                    $item->margin_type = $origin->margin_type;
                    $item->airline_name = $item->airline->name;
                    unset($item->margin_originations);
                    unset($item->airline);
                    return $item;
                })
                ->values();

            return Response::successResponse(200, 'Agency Margin List', $airline_margins);
        } catch (\Exception $e) {
            return Response::errorResponse(500, 'Record Not Found!');
        }
    }
}

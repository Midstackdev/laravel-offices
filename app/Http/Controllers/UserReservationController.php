<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class UserReservationController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->tokenCan('reservations.show'), 
            Response::HTTP_FORBIDDEN
        );

        validator(request()->all(), [
            'status' => [Rule::in([Reservation::STATUS_ACTIVE, Reservation::STATUS_CANCELLED])],
            'office_id' => ['integer'],
            'from_date' => ['date', 'require_with:to_date'],
            'to_date' => ['date', 'require_with:from_date', 'after:from_date'],
        ]);

        $reservations = Reservation::query()
            ->where('user_id', auth()->id())
            ->when(request('office_id'),
                fn ($query) => $query->where('office_id', request('office_id'))
            )->when(request('status'),
                fn ($query) => $query->where('status', request('status')) 
            )->when(
                request('from_date') && request('to_date'),
                function($query) {
                    $query->where(function($query){
                        return $query->whereBetween('start_date', [request('from_date'), request('to_date')])
                            ->orWhereBetween('end_date', [request('from_date'), request('to_date')]);
                    });
                }
            )
            ->with(['office.featuredImage'])
            ->paginate(20);

            return ReservationResource::collection($reservations);
    }
}

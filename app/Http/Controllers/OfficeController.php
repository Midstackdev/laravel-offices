<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Validators\OfficeValidator;
use App\Notifications\OfficePendingApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OfficeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $office = Office::query()
            ->when(request('user_id') && auth()->user() && request('user_id') == auth()->id(),
                fn($builder) => $builder,
                fn($builder) => $builder->where('approval_status', Office::APPPROVAL_APPROVED)->where('hidden', false)
            )
            ->when(request('user_id'), fn ($builder) => $builder->whereUserId(request('user_id')))
            ->when(request('visitor_id'), 
                fn (Builder $builder) => $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id'))
            )
            ->when(
                request('lat') && request('lng'),
                fn ($builder) => $builder->nearestTo(request('lat'), request('lng')), 
                fn ($builder) => $builder->orderBy('id', 'ASC'), 
            )
            ->with(['tags', 'images', 'user'])
            ->withCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->paginate(20);

        return OfficeResource::collection($office);
    }

    public function show(Office $office): JsonResource
    {
        $office->loadCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->load(['tags', 'images', 'user']);
        return OfficeResource::make($office);
    }

    public function create(Request $request): JsonResource
    {
        abort_unless(auth()->user()->tokenCan('office.create'), 
            Response::HTTP_FORBIDDEN
        );

        $attributes = (new OfficeValidator())->validate(
            $office = new Office(), 
            request()->all()
        );

        $attributes['approval_status'] = Office::APPPROVAL_PENDING;
        $attributes['user_id'] = auth()->id();
        $office = DB::transaction(function () use ($attributes, $office) {

            $office->fill(
                Arr::except($attributes, ['tags'])
            )->save();

            if(isset($attributes['tags'])) {
                $office->tags()->attach($attributes['tags']);
            }
            return $office;
        });

        Notification::send(User::where('is_admin', true)->get(), new OfficePendingApproval($office));

        return OfficeResource::make($office->load(['images', 'tags', 'user']));
    }

    public function update(Office $office): JsonResource
    {
        abort_unless(auth()->user()->tokenCan('office.update'), 
            Response::HTTP_FORBIDDEN
        );

        $this->authorize('update', $office);

        $attributes = (new OfficeValidator())->validate($office, request()->all());

        $office->fill(
            Arr::except($attributes, ['tags'])
        );

        if($requiresReview = $office->isDirty(['lat', 'lng', 'price_per_day'])) {
            $office->fill(['approval_status' => Office::APPPROVAL_PENDING]);
        }

        DB::transaction(function () use ($attributes, $office) {
            $office->save();

            if(isset($attributes['tags'])) {
                $office->tags()->sync($attributes['tags']);
            }
        });

        if($requiresReview) {
            Notification::send(User::where('is_admin', true)->get(), new OfficePendingApproval($office));
        }

        return OfficeResource::make($office->load(['images', 'tags', 'user']));
    }

    public function delete(Office $office)
    {
        abort_unless(auth()->user()->tokenCan('office.delete'), 
            Response::HTTP_FORBIDDEN
        );

        $this->authorize('delete', $office);

        throw_if(
            $office->hasActiveReservation(),
            ValidationException::withMessages(['office' => 'Cannot delete this office!'])
        );

        $office->delete();
    }
}

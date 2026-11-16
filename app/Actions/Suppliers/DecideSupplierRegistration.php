<?php

namespace App\Actions\Suppliers;

use App\Enums\SupplierStatus;
use App\Mail\SupplierApproved;
use App\Mail\SupplierRejected;
use App\Models\Supplier;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Mail;

class DecideSupplierRegistration
{
    public function approve(Supplier $supplier, User $admin): void
    {
        $this->denyAnAlreadyDecidedRegistration($supplier);

        $supplier->status = SupplierStatus::Approved;
        $supplier->approved_at = now();
        $supplier->approved_by = $admin->id;
        $supplier->save();

        Mail::to($supplier->user)->send(new SupplierApproved($supplier));
    }

    public function reject(Supplier $supplier): void
    {
        $this->denyAnAlreadyDecidedRegistration($supplier);

        $supplier->status = SupplierStatus::Rejected;
        $supplier->save();

        Mail::to($supplier->user)->send(new SupplierRejected($supplier));
    }

    private function denyAnAlreadyDecidedRegistration(Supplier $supplier): void
    {
        if ($supplier->status !== SupplierStatus::Pending) {
            throw new DomainException('This registration has already been decided.');
        }
    }
}

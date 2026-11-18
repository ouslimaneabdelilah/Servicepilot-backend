<?php

namespace App\Domain\Common\Enums;

enum BusinessType: string
{
    case HVAC = 'hvac';
    case PLUMBING = 'plumbing';
    case ELECTRICAL = 'electrical';
    case APPLIANCE_REPAIR = 'appliance_repair';
    case GENERAL = 'general';
}

enum CustomerType: string
{
    case RESIDENTIAL = 'residential';
    case COMMERCIAL = 'commercial';
}

enum CustomerStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case LEAD = 'lead';
}

enum ServicePricingType: string
{
    case FIXED = 'fixed';
    case HOURLY = 'hourly';
    case CUSTOM = 'custom';
}

enum RequestStatus: string
{
    case NEW = 'new';
    case PENDING = 'pending';
    case QUOTED = 'quoted';
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}

enum RequestPriority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';
}

enum AppointmentStatus: string
{
    case SCHEDULED = 'scheduled';
    case CONFIRMED = 'confirmed';
    case EN_ROUTE = 'en_route';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}

enum EstimateStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
}

enum WorkOrderStatus: string
{
    case DRAFT = 'draft';
    case ASSIGNED = 'assigned';
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case VOID = 'void';
}

enum PaymentMethod: string
{
    case CARD = 'card';
    case CASH = 'cash';
    case CHECK = 'check';
    case BANK_TRANSFER = 'bank_transfer';
    case OTHER = 'other';
}

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
}


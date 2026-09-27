<?php

namespace App\Support;

/**
 * Permission foundation (Employees step A): the abilities checked with Laravel Gates
 * (Gate::allows / @can / 'can:' route middleware -- see AuthServiceProvider).
 *
 * An admin (users.account_type 2) has every ability. An employee (account_type 1) has
 * the EMPLOYEE_DEFAULT abilities -- exactly what employees could already do -- until
 * per-employee permissions are stored (a later step adds the column; User::permissionList()
 * already reads it when present). Admin-only abilities are never granted by default.
 */
final class Permissions
{
    // employee / admin management and settings (admin only)
    const EMPLOYEES_MANAGE = 'employees.manage';     // «المسئولين»: create / edit / delete users, passwords, status, type, commission
    const SETTINGS_MANAGE = 'settings.manage';       // passwords vault, alerts, airlines, collectors, marketing prices, system tools
    const FINANCE_MANAGE = 'finance.manage';         // statements, storages, banks, bonds, expenses, customers
    const COMMISSION_SETTINGS = 'commission.settings'; // (later) commission tier tables / employee commission method

    // everyday employee work
    const INVOICES_VIEW = 'invoices.view';
    const INVOICES_CREATE = 'invoices.create';
    const INVOICES_EDIT = 'invoices.edit';
    const INVOICES_CONFIRM = 'invoices.confirm';     // confirm / un-confirm an invoice (admin only, as the list's «تأكيد العملية»)
    const REPORTS_OWN = 'reports.own';               // own sales / profit (employee report, detailed report)
    const COMMISSION_OWN = 'commission.view_own';    // (later) own commission report

    // reports on every employee (admin only)
    const REPORTS_ALL = 'reports.all';

    /** Every ability, with its label. */
    const ALL = [
        self::EMPLOYEES_MANAGE => 'إدارة الموظفين',
        self::SETTINGS_MANAGE => 'إعدادات النظام',
        self::FINANCE_MANAGE => 'الحسابات والخزينة',
        self::COMMISSION_SETTINGS => 'إعدادات العمولات',
        self::INVOICES_VIEW => 'عرض الفواتير',
        self::INVOICES_CREATE => 'إضافة فواتير',
        self::INVOICES_EDIT => 'تعديل الفواتير',
        self::INVOICES_CONFIRM => 'تأكيد الفواتير',
        self::REPORTS_OWN => 'تقارير مبيعاته وأرباحه',
        self::COMMISSION_OWN => 'تقرير عمولته',
        self::REPORTS_ALL => 'تقارير كل الموظفين',
    ];

    /** Abilities an employee has today (default until per-employee permissions exist). */
    const EMPLOYEE_DEFAULT = [
        self::INVOICES_VIEW,
        self::INVOICES_CREATE,
        self::INVOICES_EDIT,
        self::REPORTS_OWN,
        self::COMMISSION_OWN,
    ];

    /** Abilities that can never be granted to an employee (admin only). */
    const ADMIN_ONLY = [
        self::EMPLOYEES_MANAGE,
        self::SETTINGS_MANAGE,
        self::FINANCE_MANAGE,
        self::COMMISSION_SETTINGS,
        self::REPORTS_ALL,
        self::INVOICES_CONFIRM,
    ];
}

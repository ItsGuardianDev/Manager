<?php

namespace App\Enums;

enum Permission: string
{
    case EmployeesView = 'employees.view';
    case EmployeesEdit = 'employees.edit';
    case EmployeesViewPrivate = 'employees.view_private';
    case EmployeesEditPrivate = 'employees.edit_private';
    case ContractsView = 'contracts.view';
    case ContractsEdit = 'contracts.edit';
    case DocumentsView = 'documents.view';
    case DocumentsEdit = 'documents.edit';
    case LeaveApprove = 'leave.approve';
    case LeaveManage = 'leave.manage';
    case AbsenceManage = 'absence.manage';
    case ReportsView = 'reports.view';
    case AccessManage = 'access.manage';
    case ProjectsView = 'projects.view';
    case ProjectsEdit = 'projects.edit';

    public function label(): string
    {
        return match ($this) {
            self::EmployeesView => 'Medewerkers bekijken (basisgegevens)',
            self::EmployeesEdit => 'Medewerkers aanmaken en bewerken',
            self::EmployeesViewPrivate => 'Persoonlijke gegevens bekijken (adres, BSN, IBAN, geboortedatum)',
            self::EmployeesEditPrivate => 'Persoonlijke gegevens bewerken',
            self::ContractsView => 'Contracten, rooster en tarieven bekijken',
            self::ContractsEdit => 'Contracten, rooster en tarieven bewerken',
            self::DocumentsView => 'Personeelsdossier bekijken',
            self::DocumentsEdit => 'Personeelsdossier beheren',
            self::LeaveApprove => 'Verlofaanvragen goedkeuren of afwijzen',
            self::LeaveManage => 'Verlofsaldo’s en collectief verlof beheren',
            self::AbsenceManage => 'Ziekte en verzuim registreren',
            self::ReportsView => 'Verlof- en verzuimrapportages bekijken',
            self::AccessManage => 'Gebruikersgroepen, rechten en uitnodigingen beheren',
            self::ProjectsView => 'Projecten bekijken',
            self::ProjectsEdit => 'Projecten, diensten, uren en inkoop beheren',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

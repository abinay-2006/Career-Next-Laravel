<?php

namespace App\Support;

/**
 * Validation rules for the plain profile fields of each account type, shared by the profile
 * controllers and the Copilot profile-update flow so both always validate identically.
 *
 * Login identifiers (Email, UserName), Password, Photo and account Status are deliberately not
 * part of these lists; they keep their own rules in the controllers.
 */
final class ProfileFieldRules
{
    /** Rules for jobseeker_reg profile fields (as used by JobSeekerProfileController::update). */
    public static function jobSeeker(): array
    {
        return [
            'JobSeekerName' => 'required|string|max:255',
            'Address' => 'nullable|string',
            'City' => 'nullable|string|max:100',
            'Mobile' => 'nullable|string|max:20',
            'Qualification' => 'nullable|string|max:255',
            'Gender' => 'nullable|string|max:20',
            'BirthDate' => 'nullable|date',
        ];
    }

    /** Rules for employer_reg profile fields (as used by EmployerProfileController::update). */
    public static function employer(): array
    {
        return [
            'CompanyName' => ['required', 'string', 'max:255'],
            'ContactPerson' => ['nullable', 'string', 'max:255'],
            'Address' => ['nullable', 'string', 'max:1000'],
            'City' => ['nullable', 'string', 'max:100'],
            'Mobile' => ['nullable', 'string', 'max:20'],
            'Area_Work' => ['nullable', 'string', 'max:255'],
        ];
    }
}

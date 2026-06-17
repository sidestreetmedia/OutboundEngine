<?php

namespace App\Services\Leads;

/**
 * Maps a CSV's header row to lead fields. Real exports label columns a dozen
 * different ways ("Email", "Work Email", "E-mail Address"), so headers are
 * normalized to lowercase alphanumeric and matched against alias sets.
 */
class ColumnMapper
{
    /** @var array<string, list<string>> field => normalized header aliases */
    private const ALIASES = [
        'email' => ['email', 'emailaddress', 'workemail', 'mail', 'emailid', 'primaryemail'],
        'first_name' => ['firstname', 'first', 'fname', 'givenname'],
        'last_name' => ['lastname', 'last', 'lname', 'surname', 'familyname'],
        'title' => ['title', 'jobtitle', 'position', 'role'],
        'company' => ['company', 'companyname', 'organization', 'organisation', 'account', 'employer'],
        'company_domain' => ['domain', 'companydomain', 'website', 'url', 'companyurl', 'websiteurl'],
        'industry' => ['industry', 'sector', 'vertical'],
        'location' => ['location', 'city', 'country', 'region', 'state'],
        'linkedin_url' => ['linkedin', 'linkedinurl', 'linkedinprofile', 'liurl'],
    ];

    /**
     * @param  list<string>  $headers
     * @return array<int, string> column index => lead field
     */
    public function map(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalize($header);

            if ($normalized === '') {
                continue;
            }

            foreach (self::ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true) && ! in_array($field, $map, true)) {
                    $map[$index] = $field;
                    break;
                }
            }
        }

        return $map;
    }

    private function normalize(string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($header))) ?? '';
    }
}

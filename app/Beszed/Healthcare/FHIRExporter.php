<?php

namespace App\Beszed\Healthcare;

use App\Models\Child;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FHIR Exporter
 * Export patient data in FHIR format for EHR interoperability
 */
class FHIRExporter
{
    /**
     * Export child's data as FHIR Bundle
     */
    public function exportPatientBundle(Child $child): array
    {
        return [
            'resourceType' => 'Bundle',
            'type' => 'collection',
            'entry' => [
                $this->getPatientResource($child),
                $this->getObservationResources($child),
                $this->getCarePlanResource($child),
                $this->getGoalResources($child),
            ],
        ];
    }

    /**
     * FHIR Patient Resource
     */
    private function getPatientResource(Child $child): array
    {
        return [
            'resource' => [
                'resourceType' => 'Patient',
                'id' => "patient-{$child->id}",
                'identifier' => [
                    [
                        'system' => config('app.url') . '/fhir/patient',
                        'value' => $child->id,
                    ],
                ],
                'name' => [
                    [
                        'use' => 'official',
                        'given' => [$child->name],
                    ],
                ],
                'birthDate' => $child->birth_date?->format('Y-m-d'),
                'gender' => $child->gender ?? 'unknown',
                'contact' => [
                    [
                        'relationship' => [
                            [
                                'coding' => [
                                    [
                                        'system' => 'http://terminology.hl7.org/CodeSystem/v2-0131',
                                        'code' => 'N',
                                        'display' => 'Next-of-Kin',
                                    ],
                                ],
                            ],
                        ],
                        'name' => [
                            'text' => 'Parent/Guardian',
                        ],
                    ],
                ],
                'meta' => [
                    'lastUpdated' => now()->toIso8601String(),
                ],
            ],
        ];
    }

    /**
     * FHIR Observation Resources (speech metrics)
     */
    private function getObservationResources(Child $child): array
    {
        $recordings = DB::table('beszed_speech_recordings')
            ->where('child_id', $child->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $observations = [];

        foreach ($recordings as $recording) {
            $observations[] = [
                'resource' => [
                    'resourceType' => 'Observation',
                    'id' => "observation-{$recording->id}",
                    'status' => 'final',
                    'category' => [
                        [
                            'coding' => [
                                [
                                    'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                                    'code' => 'survey',
                                    'display' => 'Survey',
                                ],
                            ],
                        ],
                    ],
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://snomed.info/sct',
                                'code' => '87612001',
                                'display' => 'Speech and language assessment',
                            ],
                        ],
                    ],
                    'subject' => [
                        'reference' => "Patient/patient-{$child->id}",
                    ],
                    'effectiveDateTime' => Carbon::parse($recording->created_at)->toIso8601String(),
                    'component' => [
                        [
                            'code' => [
                                'coding' => [
                                    [
                                        'system' => 'http://loinc.org',
                                        'code' => '12345-1',
                                        'display' => 'Pronunciation Score',
                                    ],
                                ],
                            ],
                            'valueQuantity' => [
                                'value' => $recording->pronunciation_score,
                                'unit' => '%',
                                'system' => 'http://unitsofmeasure.org',
                                'code' => '%',
                            ],
                        ],
                        [
                            'code' => [
                                'coding' => [
                                    [
                                        'system' => 'http://loinc.org',
                                        'code' => '12346-1',
                                        'display' => 'Fluency Score',
                                    ],
                                ],
                            ],
                            'valueQuantity' => [
                                'value' => $recording->fluency_score,
                                'unit' => '%',
                                'system' => 'http://unitsofmeasure.org',
                                'code' => '%',
                            ],
                        ],
                        [
                            'code' => [
                                'coding' => [
                                    [
                                        'system' => 'http://loinc.org',
                                        'code' => '12347-1',
                                        'display' => 'Clarity Score',
                                    ],
                                ],
                            ],
                            'valueQuantity' => [
                                'value' => $recording->clarity_score,
                                'unit' => '%',
                                'system' => 'http://unitsofmeasure.org',
                                'code' => '%',
                            ],
                        ],
                    ],
                    'meta' => [
                        'lastUpdated' => Carbon::parse($recording->created_at)->toIso8601String(),
                    ],
                ],
            ];
        }

        return $observations;
    }

    /**
     * FHIR CarePlan Resource
     */
    private function getCarePlanResource(Child $child): array
    {
        $avgPronunciation = DB::table('beszed_speech_recordings')
            ->where('child_id', $child->id)
            ->where('created_at', '>=', now()->subMonth())
            ->avg('pronunciation_score') ?? 0;

        return [
            'resource' => [
                'resourceType' => 'CarePlan',
                'id' => "careplan-{$child->id}",
                'status' => 'active',
                'intent' => 'plan',
                'subject' => [
                    'reference' => "Patient/patient-{$child->id}",
                ],
                'period' => [
                    'start' => now()->startOfMonth()->toIso8601String(),
                ],
                'category' => [
                    [
                        'coding' => [
                            [
                                'system' => 'http://hl7.org/fhir/care-plan-category',
                                'code' => 'therapy',
                                'display' => 'Speech Therapy',
                            ],
                        ],
                    ],
                ],
                'activity' => [
                    [
                        'detail' => [
                            'kind' => 'Task',
                            'code' => [
                                'coding' => [
                                    [
                                        'system' => 'http://snomed.info/sct',
                                        'code' => '87612001',
                                        'display' => 'Speech and language therapy',
                                    ],
                                ],
                            ],
                            'status' => 'in-progress',
                            'doNotPerform' => false,
                            'scheduledTiming' => [
                                'repeat' => [
                                    'frequency' => 5,
                                    'period' => 1,
                                    'periodUnit' => 'wk',
                                ],
                            ],
                        ],
                    ],
                ],
                'meta' => [
                    'lastUpdated' => now()->toIso8601String(),
                ],
            ],
        ];
    }

    /**
     * FHIR Goal Resources
     */
    private function getGoalResources(Child $child): array
    {
        return [
            [
                'resource' => [
                    'resourceType' => 'Goal',
                    'id' => "goal-{$child->id}-1",
                    'lifecycleStatus' => 'active',
                    'description' => [
                        'text' => 'Achieve 80% pronunciation accuracy',
                    ],
                    'subject' => [
                        'reference' => "Patient/patient-{$child->id}",
                    ],
                    'target' => [
                        [
                            'measure' => [
                                'coding' => [
                                    [
                                        'system' => 'http://loinc.org',
                                        'code' => '12345-1',
                                        'display' => 'Pronunciation Score',
                                    ],
                                ],
                            ],
                            'detailQuantity' => [
                                'value' => 80,
                                'unit' => '%',
                                'system' => 'http://unitsofmeasure.org',
                                'code' => '%',
                            ],
                            'dueDate' => now()->addMonths(3)->format('Y-m-d'),
                        ],
                    ],
                    'meta' => [
                        'lastUpdated' => now()->toIso8601String(),
                    ],
                ],
            ],
        ];
    }

    /**
     * Export as JSON
     */
    public function exportAsJson(Child $child): string
    {
        return json_encode($this->exportPatientBundle($child), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Export as XML
     */
    public function exportAsXml(Child $child): string
    {
        $bundle = $this->exportPatientBundle($child);
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Bundle/>');

        $this->arrayToXml($bundle, $xml);

        return $xml->asXML();
    }

    /**
     * Convert array to XML
     */
    private function arrayToXml(array $array, \SimpleXMLElement $xml): void
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $subnode = $xml->addChild($key);
                    $this->arrayToXml($value, $subnode);
                } else {
                    $this->arrayToXml($value, $xml);
                }
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }
}

<?php

namespace App\Observers;

use App\Models\Service;
use Illuminate\Support\Facades\Log;

class ServiceObserver
{
    /**
     * Handle the Service "created" event.
     */
    public function created(Service $service): void
    {
        //
    }

    /**
     * Handle the Service "updated" event.
     */
    public function updated(Service $service): void
    {
        // Check if the service status was changed to 'completed'
        if ($service->isDirty('status') && $service->status === 'completed') {
            Log::info("Service #{$service->id} marked as completed, updating mechanic reports");
            
            // Get all mechanics for this service
            $mechanics = $service->mechanics;
            
            if ($mechanics->count() > 0) {
                Log::info("Service has {$mechanics->count()} mechanics, generating reports");
                
                // Get the week start and end dates
                $weekStart = now()->startOfWeek();
                $weekEnd = now()->endOfWeek();
                
                // Update mechanic_service pivot with week dates if not already set
                foreach ($mechanics as $mechanic) {
                    if (empty($mechanic->pivot->week_start) || empty($mechanic->pivot->week_end)) {
                        Log::info("Updating week dates for mechanic #{$mechanic->id}");
                        
                        $service->mechanics()->updateExistingPivot($mechanic->id, [
                            'week_start' => $weekStart,
                            'week_end' => $weekEnd,
                        ]);
                    }
                }
                
                // Generate reports for all mechanics
                $this->generateMechanicReports($service);
            }
        }
    }

    /**
     * Handle the Service "deleted" event.
     */
    public function deleted(Service $service): void
    {
        //
    }

    /**
     * Handle the Service "restored" event.
     */
    public function restored(Service $service): void
    {
        //
    }

    /**
     * Handle the Service "force deleted" event.
     */
    public function forceDeleted(Service $service): void
    {
        //
    }
    
    /**
     * Generate mechanic reports for all mechanics in a service.
     */
    protected function generateMechanicReports($service): void
    {
        // Refresh the service to get the latest data
        $service->refresh();
        
        // Get all mechanics for this service
        $mechanics = $service->mechanics;
        
        // Log for debugging
        Log::info("Generating mechanic reports for service #{$service->id} with " . $mechanics->count() . " mechanics");
        
        // Process each mechanic
        foreach ($mechanics as $mechanic) {
            try {
                // Get week start and end from pivot
                $weekStart = $mechanic->pivot->week_start;
                $weekEnd = $mechanic->pivot->week_end;
                
                // If week dates are not set, use current week
                if (empty($weekStart) || empty($weekEnd)) {
                    $weekStart = now()->startOfWeek();
                    $weekEnd = now()->endOfWeek();
                    
                    // Update pivot with week dates
                    $service->mechanics()->updateExistingPivot($mechanic->id, [
                        'week_start' => $weekStart,
                        'week_end' => $weekEnd,
                    ]);
                }
                
                Log::info("Mechanic #{$mechanic->id} week period: {$weekStart} to {$weekEnd}");
                
                // Force refresh mechanic from database
                $freshMechanic = \App\Models\Mechanic::find($mechanic->id);
                
                // Generate or update weekly report for this mechanic
                $report = $freshMechanic->generateWeeklyReport($weekStart, $weekEnd);
                
                Log::info("Successfully generated report for mechanic #{$mechanic->id} ({$mechanic->name})", [
                    'report_id' => $report->id,
                    'services_count' => $report->services_count,
                    'total_labor_cost' => $report->total_labor_cost,
                ]);
            } catch (\Exception $e) {
                // Log error but continue with other mechanics
                Log::error("Error generating report for mechanic #{$mechanic->id}: " . $e->getMessage(), [
                    'service_id' => $service->id,
                    'mechanic_id' => $mechanic->id,
                    'exception' => $e
                ]);
            }
        }
    }
}

<?php

use Illuminate\Support\Facades\Route;

Route::get('/debug-oee-request', function() {
    $request = request();
    
    echo "=== OEE Dashboard Request Debug ===\n\n";
    echo "All Request Parameters:\n";
    print_r($request->all());
    
    echo "\n\nPlant ID: " . ($request->input('plant_id') ?? 'NULL') . "\n";
    echo "Line ID: " . ($request->input('line_id') ?? 'NULL') . "\n";
    echo "Machine ID: " . ($request->input('machine_id') ?? 'NULL') . "\n";
    
    echo "\n\n=== Available Data ===\n";
    echo "Total Plants: " . \App\Models\Plant::count() . "\n";
    echo "Total Lines: " . \App\Models\Line::count() . "\n";
    echo "Total Machines: " . \App\Models\Machine::count() . "\n";
    echo "Total Shifts: " . \App\Models\ProductionShift::where('status', 'completed')->count() . "\n";
    
    echo "\n\nShifts by Machine:\n";
    $shiftsByMachine = \App\Models\ProductionShift::where('status', 'completed')
        ->select('machine_id', \DB::raw('count(*) as count'))
        ->groupBy('machine_id')
        ->get();
    foreach ($shiftsByMachine as $row) {
        $machine = \App\Models\Machine::find($row->machine_id);
        echo "Machine {$row->machine_id} ({$machine->name}): {$row->count} shifts\n";
    }
});

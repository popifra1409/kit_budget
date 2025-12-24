<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Imports\NomenclatureImport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ImportNomenclature extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:nomenclature 
                            {file : Chemin vers le fichier Excel} 
                            {--sheet= : Nom de la feuille à importer (DEPENSES ou RECETTES)}
                            {--date= : Date de début de validité (format: Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importer la nomenclature budgétaire depuis un fichier Excel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $sheet = $this->option('sheet');
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::now()->startOfYear();

        // Vérifier que le fichier existe
        if (!file_exists($filePath)) {
            $this->error("Le fichier {$filePath} n'existe pas !");
            return 1;
        }

        $this->info("📊 Importation de la nomenclature budgétaire...");
        $this->info("📁 Fichier : {$filePath}");

        if ($sheet) {
            $this->info("📄 Feuille : {$sheet}");
        }

        $this->info("📅 Date de validité : {$date->format('d/m/Y')}");
        $this->newLine();

        try {
            $import = new NomenclatureImport($date);

            if ($sheet) {
                Excel::import($import, $filePath, null, \Maatwebsite\Excel\Excel::XLSX);
            } else {
                Excel::import($import, $filePath);
            }

            $this->info("✅ Importation réussie !");
        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de l'importation : " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}

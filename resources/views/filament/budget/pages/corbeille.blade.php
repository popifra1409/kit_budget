{{-- resources/views/filament/budget/pages/corbeille.blade.php --}}
<x-filament-panels::page>
    {{ $this->form }}

    <x-filament::section>
        <div class="grid gap-2 text-sm md:grid-cols-3">
            <div>
                <div class="font-semibold">Ce que la liste montre</div>
                <p class="text-gray-500 dark:text-gray-400">
                    Toutes les lignes mortes des tables que la corbeille connaît, <strong>tous exercices
                    et tous responsables confondus</strong> — le filtre exercice masquerait justement ce
                    qu'on vient chercher ici.
                </p>
            </div>
            <div>
                <div class="font-semibold">Le jeton</div>
                <p class="text-gray-500 dark:text-gray-400">
                    Une ligne avec un jeton revient <strong>avec les siennes</strong> : le trait
                    <code>HasCorbeille</code> ressuscite tout ce qui porte le même jeton. Une ligne
                    « aucun — revient seul » était jetée avant le câblage, elle revient seule.
                </p>
            </div>
            <div>
                <div class="font-semibold">Un refus reste un refus</div>
                <p class="text-gray-500 dark:text-gray-400">
                    « Faire revenir » est affiché même quand il refusera : un ascendant encore mort, une
                    ligne déjà revenue, une clé unique qui coince sont <strong>dits</strong>, pas cachés.
                    La confirmation annonce ce qui reviendra et ce qui restera mort.
                </p>
            </div>
        </div>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>

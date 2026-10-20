@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div>
        <p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Seguridad</p>
        <h1 class="text-3xl font-black">Backups de base de datos y archivos</h1>
        <p class="mt-2 text-sm text-gray-500">Los ZIP incluyen la base de datos completa, archivos subidos y checksums de integridad. La ejecución automática requiere configurar el programador del servidor.</p>
    </div>

    @if (session('success'))
        <div class="border-l-4 border-green-600 bg-green-50 p-4 font-semibold text-green-800" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="border-l-4 border-red-600 bg-red-50 p-4 font-semibold text-red-800" role="alert">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->has('confirmation'))
        <div class="border-l-4 border-red-600 bg-red-50 p-4 font-semibold text-red-800" role="alert">
            {{ $errors->first('confirmation') }}
        </div>
    @endif

    <div class="overflow-x-auto rounded-lg bg-white shadow-md">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-[#f7e8e6] text-xs uppercase text-[#8f241d]">
                <tr>
                    <th class="px-5 py-3">Archivo</th>
                    <th class="px-5 py-3">Fecha</th>
                    <th class="px-5 py-3">Tamaño</th>
                    <th class="px-5 py-3">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($backups as $backup)
                    @php($isRestorable = str_ends_with($backup['name'], '.zip'))
                    <tr>
                        <td class="px-5 py-3 font-mono">{{ $backup['name'] }}</td>
                        <td class="px-5 py-3">{{ date('d/m/Y H:i', $backup['date']) }}</td>
                        <td class="px-5 py-3">{{ number_format($backup['size'] / 1024, 1, ',', '.') }} KB</td>
                        <td class="px-5 py-3">
                            <div class="flex flex-wrap items-center gap-4">
                                <a href="{{ route('backups.download', $backup['name']) }}" class="font-bold text-[#9f2f25] hover:text-[#721d18]">Descargar</a>
                                @if ($isRestorable)
                                    <button
                                        type="button"
                                        class="font-bold text-red-700 hover:text-red-900"
                                        data-restore-url="{{ route('backups.restore', $backup['name']) }}"
                                        data-backup-filename="{{ $backup['name'] }}"
                                    >Restaurar</button>
                                @else
                                    <span class="text-xs text-gray-500" title="Los respaldos antiguos no tienen verificación de integridad">Formato antiguo: solo descarga</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-6 text-center text-gray-500">Todavía no hay backups generados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="backup-restore-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="backup-restore-title">
    <div class="mx-auto mt-12 max-w-xl rounded-lg bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase tracking-wider text-red-700">Acción irreversible</p>
                <h2 id="backup-restore-title" class="mt-1 text-2xl font-black text-gray-900">Restaurar backup</h2>
            </div>
            <button type="button" id="backup-restore-close" class="text-2xl text-gray-500 hover:text-gray-900" aria-label="Cerrar">&times;</button>
        </div>

        <div class="mt-5 space-y-4 text-sm text-gray-700">
            <p>Estás por restaurar <strong id="backup-restore-filename" class="break-all font-mono"></strong>.</p>
            <div class="border-l-4 border-red-600 bg-red-50 p-4 text-red-900">
                <p class="font-black">Advertencia 1 de 2</p>
                <p class="mt-1">Se reemplazarán la base de datos actual y los archivos subidos. Los cambios realizados después de este backup se perderán.</p>
            </div>
            <p>Antes de restaurar, el sistema intentará crear un backup preventivo del estado actual. Si ese backup falla, la restauración no continuará.</p>
        </div>

        <div id="backup-restore-final-step" class="mt-5 hidden">
            <div class="border-l-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-950">
                <p class="font-black">Advertencia 2 de 2</p>
                <p class="mt-1">Confirma que seleccionaste el archivo correcto. Esta acción no se puede deshacer desde esta pantalla.</p>
            </div>
            <label for="backup-restore-confirmation" class="mt-4 block text-sm font-bold text-gray-800">
                Escribe <span class="font-mono text-red-700">RESTAURAR</span> para habilitar la restauración.
            </label>
            <input id="backup-restore-confirmation" name="confirmation" form="backup-restore-form" type="text" autocomplete="off" class="mt-2 w-full rounded border border-gray-300 p-3 font-mono focus:border-red-700 focus:outline-none">
        </div>

        <form id="backup-restore-form" method="POST" class="mt-6 flex flex-wrap justify-end gap-3">
            @csrf
            <button type="button" id="backup-restore-cancel" class="rounded border border-gray-300 px-4 py-2 font-bold text-gray-700 hover:bg-gray-100">Cancelar</button>
            <button type="button" id="backup-restore-continue" class="rounded bg-red-700 px-4 py-2 font-bold text-white hover:bg-red-800">Entiendo, continuar</button>
            <button type="submit" id="backup-restore-submit" class="hidden rounded bg-red-700 px-4 py-2 font-bold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50" disabled>Restaurar backup</button>
        </form>
    </div>
</div>

<script>
    (() => {
        const modal = document.getElementById('backup-restore-modal');
        const form = document.getElementById('backup-restore-form');
        const filename = document.getElementById('backup-restore-filename');
        const finalStep = document.getElementById('backup-restore-final-step');
        const confirmation = document.getElementById('backup-restore-confirmation');
        const continueButton = document.getElementById('backup-restore-continue');
        const submitButton = document.getElementById('backup-restore-submit');
        const closeButtons = [
            document.getElementById('backup-restore-close'),
            document.getElementById('backup-restore-cancel'),
        ];

        const closeModal = () => {
            modal.classList.add('hidden');
            finalStep.classList.add('hidden');
            continueButton.classList.remove('hidden');
            submitButton.classList.add('hidden');
            submitButton.disabled = true;
            confirmation.value = '';
        };

        document.querySelectorAll('[data-restore-url]').forEach(button => {
            button.addEventListener('click', () => {
                form.action = button.dataset.restoreUrl;
                filename.textContent = button.dataset.backupFilename;
                modal.classList.remove('hidden');
                document.getElementById('backup-restore-continue').focus();
            });
        });

        continueButton.addEventListener('click', () => {
            finalStep.classList.remove('hidden');
            continueButton.classList.add('hidden');
            submitButton.classList.remove('hidden');
            confirmation.focus();
        });

        confirmation.addEventListener('input', () => {
            submitButton.disabled = confirmation.value.trim() !== 'RESTAURAR';
        });

        form.addEventListener('submit', event => {
            if (confirmation.value.trim() !== 'RESTAURAR') {
                event.preventDefault();
            }
        });

        closeButtons.forEach(button => button.addEventListener('click', closeModal));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    })();
</script>
@endsection

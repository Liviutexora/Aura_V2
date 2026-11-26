@if(config('pwa.enabled'))
<section class="card pwa-install" data-pwa-install-panel hidden aria-labelledby="pwa-install-title">
    <div class="heading">
        <div>
            <div class="eyebrow">APLICAȚIE INSTALABILĂ</div>
            <h2 id="pwa-install-title">Instalează AURA</h2>
            <p data-pwa-status>Accesează AURA direct de pe ecranul dispozitivului.</p>
        </div>
        <button class="btn" type="button" data-pwa-install>Instalează AURA</button>
    </div>
    <details data-pwa-manual>
        <summary>Instrucțiuni pentru instalare manuală</summary>
        <p><strong>iPhone sau iPad:</strong> deschide meniul Partajare din Safari și alege „Adaugă la ecranul principal”.</p>
        <p><strong>Safari pe Mac:</strong> folosește „File → Add to Dock”.</p>
        <p><strong>Alte browsere:</strong> caută opțiunea „Instalează aplicația” sau „Adaugă la ecranul principal” în meniul browserului.</p>
    </details>
</section>
@endif

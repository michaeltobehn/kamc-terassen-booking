<?php
declare(strict_types=1);
require __DIR__ . '/../src/layout.php';

$user = require_login();

page_start('Kalender', $user, 'kalender');
page_header('Belegungskalender', 'Grün = frei, rot = belegt. Auf einen freien Slot klicken, um zu buchen.');
?>
<div class="container-page" x-data="cal()" x-init="load()">
    <div class="card p-4 sm:p-6">
        <!-- Steuerung -->
        <div class="flex items-center justify-between mb-5">
            <button class="btn-ghost btn-sm" @click="prev()" aria-label="Vorheriger Monat">←</button>
            <h2 class="text-lg sm:text-xl font-display font-semibold text-navy" x-text="monthLabel"></h2>
            <button class="btn-ghost btn-sm" @click="next()" aria-label="Nächster Monat">→</button>
        </div>

        <!-- Legende -->
        <div class="flex flex-wrap gap-3 mb-5 text-xs">
            <span class="badge badge-frei">frei</span>
            <span class="badge badge-belegt">belegt</span>
            <span class="badge badge-blackout">gesperrt</span>
            <span class="badge badge-geschlossen">geschlossen</span>
            <span class="badge badge-vergangen">vergangen</span>
        </div>

        <!-- Wochentage -->
        <div class="grid grid-cols-7 gap-1.5 mb-1.5 text-center text-xs font-ui font-semibold text-schiefer">
            <template x-for="d in ['Mo','Di','Mi','Do','Fr','Sa','So']" :key="d"><div x-text="d"></div></template>
        </div>

        <!-- Tage -->
        <div class="grid grid-cols-7 gap-1.5">
            <template x-for="cell in cells" :key="cell.key">
                <div>
                    <template x-if="cell.blank">
                        <div class="min-h-[4.5rem]"></div>
                    </template>
                    <template x-if="!cell.blank">
                        <div class="cal-cell" :class="cell.today ? 'ring-2 ring-navy/40' : ''">
                            <div class="text-xs font-ui font-semibold mb-1" :class="cell.today ? 'text-navy' : 'text-schiefer'" x-text="cell.day"></div>
                            <div class="space-y-1">
                                <template x-for="slot in ['tag','abend']" :key="slot">
                                    <a :href="cell.status[slot]==='frei' ? ('/buchen.php?date='+cell.date+'&slot='+slot) : null"
                                       class="slot-chip"
                                       :class="chipClass(cell.status[slot])"
                                       x-text="(slot==='tag'?'Tag':'Abend')+' · '+labelOf(cell.status[slot])"></a>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div x-show="loading" class="mt-4 text-sm text-schiefer">Lade Verfügbarkeit…</div>
        <div x-show="error" x-cloak class="mt-4 text-sm text-akzent-700" x-text="error"></div>
    </div>
</div>

<script>
function cal() {
    const today = new Date();
    return {
        ym: { y: today.getFullYear(), m: today.getMonth() }, // m: 0-11
        cells: [], loading: false, error: '',
        get monthLabel() {
            const names = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
            return names[this.ym.m] + ' ' + this.ym.y;
        },
        pad(n){ return String(n).padStart(2,'0'); },
        iso(y,m,d){ return y+'-'+this.pad(m+1)+'-'+this.pad(d); },
        labelOf(s){ return ({frei:'frei',belegt:'belegt',blackout:'gesperrt',geschlossen:'zu',vergangen:'—'})[s] || s; },
        chipClass(s){
            return ({
                frei:'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 hover:bg-emerald-100 cursor-pointer',
                belegt:'bg-akzent/10 text-akzent-700 ring-1 ring-akzent/20 cursor-not-allowed',
                blackout:'bg-navy/10 text-navy ring-1 ring-navy/15 cursor-not-allowed',
                geschlossen:'bg-nebel text-schiefer cursor-not-allowed',
                vergangen:'bg-nebel/60 text-schiefer/60 cursor-not-allowed'
            })[s] || 'bg-nebel';
        },
        prev(){ this.ym.m--; if(this.ym.m<0){this.ym.m=11;this.ym.y--;} this.load(); },
        next(){ this.ym.m++; if(this.ym.m>11){this.ym.m=0;this.ym.y++;} this.load(); },
        async load() {
            this.loading = true; this.error = '';
            const y = this.ym.y, m = this.ym.m;
            const first = new Date(y, m, 1);
            const daysInMonth = new Date(y, m+1, 0).getDate();
            const lead = (first.getDay()+6)%7; // Mo=0
            const from = this.iso(y,m,1), to = this.iso(y,m,daysInMonth);
            let data = [];
            try {
                const res = await fetch('/availability.php?from='+from+'&to='+to);
                if(!res.ok) throw new Error('HTTP '+res.status);
                data = await res.json();
                if(data.error) throw new Error(data.error);
            } catch(err) { this.error = 'Verfügbarkeit konnte nicht geladen werden.'; this.loading=false; return; }
            const byDate = {}; data.forEach(d => byDate[d.date] = d);
            const cells = [];
            for(let i=0;i<lead;i++) cells.push({blank:true, key:'b'+i});
            const tIso = this.iso(today.getFullYear(), today.getMonth(), today.getDate());
            for(let d=1; d<=daysInMonth; d++){
                const date = this.iso(y,m,d);
                const s = byDate[date] || {tag:'geschlossen',abend:'geschlossen'};
                cells.push({blank:false, key:date, date, day:d, today: date===tIso, status:{tag:s.tag, abend:s.abend}});
            }
            this.cells = cells; this.loading = false;
        }
    };
}
</script>
<?php
page_end();

<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';

const stepElements = ref([]);
const howItWorksSteps = [
  { icon: 'https://img.icons8.com/fluency/96/upload-to-cloud.png', title: '1. Carica il tuo preventivo', text: 'Il primo passo è semplicissimo. Fai una foto chiara o una scansione del preventivo che hai ricevuto e caricalo sulla nostra piattaforma. Accettiamo i formati più comuni come PDF, JPG o PNG. Il processo è completamente anonimo per proteggere la tua privacy. I dentisti vedranno solo le cure richieste, non i tuoi dati personali.' },
  { icon: 'https://img.icons8.com/fluency/96/get-revenue.png', title: '2. Ricevi le proposte', text: 'Una volta caricato il tuo preventivo, questo verrà inviato in forma anonima ai dentisti registrati nella tua area geografica. I professionisti interessati analizzeranno le voci del preventivo e, se possono offrirti condizioni migliori, ti invieranno una loro controproposta economica direttamente sulla piattaforma. Potrai vedere le offerte comodamente dal tuo profilo.' },
  { icon: 'https://img.icons8.com/color/96/choose.png', title: '3. Scegli il migliore', text: 'Ora tocca a te! Analizza tutte le proposte ricevute. Potrai confrontare non solo i prezzi, ma anche consultare i profili dei dentisti, vedere dove si trovano i loro studi e leggere le recensioni lasciate da altri pazienti come te. Questa trasparenza ti permette di fare una scelta informata, basata non solo sul risparmio ma anche sulla fiducia.' },
  { icon: 'https://img.icons8.com/fluency/96/tear-off-calendar.png', title: "4. Prenota la tua visita'", text: "Una volta scelto il medico e il preventivo che fanno per te, potrai prenotare una prima visita di controllo. Questo appuntamento è fondamentale per conoscere di persona il professionista, verificare lo stato di salute della tua bocca e discutere nel dettaglio il piano di cura proposto. È l'ultimo passo per iniziare il tuo percorso di cura con serenità e convenienza." }
];

onMounted(() => {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.2 });

  stepElements.value.forEach(el => {
    if (el) observer.observe(el);
  });
});
</script>

<template>
  <div style="margin-top: 10vh;">
    <div class="container text-center py-5">
      <h1 class="display-4 fw-bold text-primary">Come Funziona il Nostro Servizio</h1>
      <p class="lead col-lg-8 mx-auto text-muted">
        Ottenere un preventivo migliore non è mai stato così facile. Segui questi quattro semplici passi per iniziare a risparmiare.
      </p>
    </div>

    <div class="container py-5">
      <div class="timeline-container mx-auto">
        <div v-for="(step, index) in howItWorksSteps" :key="index" class="timeline-step" :ref="el => { if (el) stepElements[index] = el }">
          <div class="timeline-icon bg-primary text-white">
            <img :src="step.icon" :alt="step.title" height="80" class="mb-3">
          </div>
          <div class="timeline-content">
            <h3 class="fw-semibold">{{ step.title }}</h3>
            <p class="text-muted">{{ step.text }}</p>
          </div>
        </div>
      </div>
    </div>

    <section class="py-5 bg-primary text-white text-center">
      <div class="container">
        <h2 class="display-6 fw-bold text-white">Sei pronto a risparmiare?</h2>
        <p class="lead my-4">Inizia ora. È gratuito, anonimo e senza impegno.</p>
        <RouterLink to="/login" class="btn btn-light btn-lg px-4 fw-bold text-primary">Carica il tuo preventivo ora</RouterLink>
      </div>
    </section>
  </div>
</template>

<style scoped>
.timeline-container {
  position: relative;
  max-width: 800px;
}

.timeline-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 39px;
  width: 4px;
  height: 100%;
  background-color: #e9ecef;
  border-radius: 2px;
}

.timeline-step {
  position: relative;
  display: flex;
  align-items: flex-start;
  margin-bottom: 8rem;
  opacity: 0;
  transform: translateY(30px);
  transition: opacity 0.6s ease-out, transform 0.6s ease-out;
}

.timeline-step.is-visible {
  opacity: 1;
  transform: translateY(0);
}

.timeline-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 5px solid white;
  box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
  z-index: 10;
}

.timeline-content {
  margin-left: 2rem;
}
</style>
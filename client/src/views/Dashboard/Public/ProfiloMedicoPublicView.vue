<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import axios from 'axios'
import { useToast } from 'vue-toastification'
import logoSrc from '@/assets/images/logo-IDM.png'

const route = useRoute()
const toast = useToast()

const profiloPubblico = ref(null)
const isLoading = ref(false)
const error = ref(null)

onMounted(async () => {
  const medicoId = route.params.id
  await fetchProfiloPubblico(medicoId)
})

const fetchProfiloPubblico = async (medicoId) => {
  isLoading.value = true
  error.value = null
  try {
    const response = await axios.get(`/api/profilo-pubblico-medico/${medicoId}`)
    profiloPubblico.value = {
      anagrafica: response.data.anagrafica_medico,
      fotoStudi: response.data.foto_studi,
      staff: response.data.staff,
    }
  } catch (err) {
    const message = err.response?.status === 403
      ? 'Il profilo di questo medico non è ancora completo.'
      : err.response?.status === 404
      ? 'Medico non trovato.'
      : 'Errore nel caricamento del profilo.'
    error.value = message
    toast.error(message)
  } finally {
    isLoading.value = false
  }
}

// Funzione per ottenere l'URL completo dell'asset
const getAssetUrl = (path) => {
  return `${import.meta.env.VITE_API_URL}/storage/${path}`
}
</script>

<template>
  <div class="container-fluid px-3 px-md-4 py-4 py-md-5">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-10 col-xl-9">
        <!-- Logo -->
        <div class="text-center mb-3 mb-md-4">
          <RouterLink to="/">
            <img :src="logoSrc" alt="Il Dentista Migliore Logo" class="logo-responsive">
          </RouterLink>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="card border-0 shadow-sm">
          <div class="card-body text-center p-3 p-sm-4 p-md-5">
            <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem"></div>
            <h4 class="mt-3 mt-md-4 fs-5 fs-md-4">Caricamento profilo...</h4>
          </div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="card border-0 shadow-sm">
          <div class="card-body p-3 p-sm-4 p-md-5 text-center">
            <i class="fa-solid fa-circle-exclamation fa-3x fa-md-4x text-warning mb-3 mb-md-4"></i>
            <h3 class="fw-bold fs-4 fs-md-3 mb-3">{{ error }}</h3>
            <button @click="$router.go(-1)" class="btn btn-secondary">
              <i class="fa-solid fa-arrow-left me-2"></i>Torna Indietro
            </button>
          </div>
        </div>

        <!-- Profilo -->
        <div v-else-if="profiloPubblico">
          <!-- Hero Section -->
          <div class="card border-0 shadow-sm mb-3 mb-md-4">
            <div class="hero-section text-center p-3 p-sm-4 p-md-5 rounded-3">
              <h1 class="responsive-title fw-bold text-white mb-2 mb-md-3">{{ profiloPubblico.anagrafica.ragione_sociale }}</h1>
              <p class="responsive-lead text-white-50 mb-0">
                <i class="fa-solid fa-location-dot me-2"></i>
                {{ profiloPubblico.anagrafica.indirizzo }}, {{ profiloPubblico.anagrafica.citta }} ({{ profiloPubblico.anagrafica.provincia }})
              </p>
            </div>
          </div>

          <!-- Chi Siamo -->
          <div class="card border-0 shadow-sm mb-3 mb-md-4">
            <div class="card-body p-3 p-sm-4 p-md-4">
              <h2 class="card-title fs-5 fs-md-4 mb-3">
                <i class="fa-solid fa-circle-info me-2 text-primary"></i>Chi Siamo
              </h2>
              <p class="card-text text-muted mb-0" style="white-space: pre-wrap;">{{ profiloPubblico.anagrafica.descrizione }}</p>
            </div>
          </div>

          <!-- Galleria Studio -->
          <div v-if="profiloPubblico.fotoStudi && profiloPubblico.fotoStudi.length > 0" class="card border-0 shadow-sm mb-3 mb-md-4">
            <div class="card-body p-3 p-sm-4 p-md-4">
              <h2 class="card-title fs-5 fs-md-4 mb-3 mb-md-4">
                <i class="fa-solid fa-images me-2 text-primary"></i>Galleria dello Studio
              </h2>
              <div class="row g-2 g-md-3">
                <div v-for="foto in profiloPubblico.fotoStudi" :key="foto.id" class="col-6 col-md-4">
                  <div class="gallery-card">
                    <img :src="getAssetUrl(foto.file_path)" class="img-fluid rounded" :alt="'Foto dello studio ' + profiloPubblico.anagrafica.ragione_sociale">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Staff -->
          <div v-if="profiloPubblico.staff && profiloPubblico.staff.length > 0" class="card border-0 shadow-sm mb-3 mb-md-4">
            <div class="card-body p-3 p-sm-4 p-md-4">
              <h2 class="card-title fs-5 fs-md-4 mb-3 mb-md-4">
                <i class="fa-solid fa-users me-2 text-primary"></i>Il Nostro Staff
              </h2>
              <div class="row g-3 g-md-4">
                <div v-for="membro in profiloPubblico.staff" :key="membro.id" class="col-12 col-sm-6 col-md-4">
                  <div class="card text-center staff-card h-100 border-0 shadow-sm">
                    <img :src="getAssetUrl(membro.foto_path)" class="staff-img rounded-circle mx-auto mt-3" :alt="'Foto di ' + membro.nome">
                    <div class="card-body p-3">
                      <h5 class="card-title fs-6 fs-md-5 mb-1">{{ membro.nome }}</h5>
                      <h6 class="card-subtitle mb-2 text-primary small">{{ membro.ruolo }}</h6>
                      <p v-if="membro.specializzazione" class="card-text text-muted small mb-1">{{ membro.specializzazione }}</p>
                      <p v-if="membro.esperienza" class="card-text text-muted small mb-0"><em>{{ membro.esperienza }}</em></p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Recensioni -->
          <div class="card border-0 shadow-sm mb-3 mb-md-4">
            <div class="card-body p-3 p-sm-4 p-md-4">
              <h2 class="card-title fs-5 fs-md-4 mb-3 mb-md-4">
                <i class="fa-solid fa-star me-2 text-primary"></i>Recensioni dei Pazienti
              </h2>
              <div class="row g-3 g-md-4">
                <div class="col-12 col-md-4">
                  <div class="card review-card h-100 border-0 bg-light">
                    <div class="card-body p-3">
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0 fs-6">Maria Rossi</h5>
                        <div class="stars small">
                          <i class="fa-solid fa-star text-warning"></i>
                          <i class="fa-solid fa-star text-warning"></i>
                          <i class="fa-solid fa-star text-warning"></i>
                          <i class="fa-solid fa-star text-warning"></i>
                          <i class="fa-solid fa-star text-warning"></i>
                        </div>
                      </div>
                      <p class="card-text text-muted small mb-0">Esperienza eccellente! Personale professionale e molto disponibile. Lo studio è moderno e pulito. Consiglio vivamente!</p>
                    </div>
                  </div>
                </div>
                <div class="col-12 col-md-4">
                  <div class="card review-card h-100 border-0 bg-light">
                    <div class="card-body p-3">
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0 fs-6">Giovanni Bianchi</h5>
                        <div class="stars small">
                          <i class="fa-solid fa-star text-warning"></i>
                          <i class="fa-solid fa-star text-warning"></i>
                          <i class="fa-solid fa-star text-warning"></i>
                          <i class="fa-solid fa-star text-warning"></i>
                          <i class="fa-solid fa-star text-warning"></i>
                        </div>
                      </div>
                      <p class="card-text text-muted small mb-0">Ottimo rapporto qualità-prezzo. I medici sono competenti e attenti alle esigenze del paziente. Molto soddisfatto del servizio.</p>
                    </div>
                  </div>
                </div>
                <div class="col-12 col-md-4">
                  <div class="card review-card h-100 border-0 bg-light">
                    <div class="card-body p-3">
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0 fs-6">Laura Verdi</h5>
                        <div class="stars small">
                          <i class="fa-solid fa-star text-warning"></i>
                          <i class="fa-solid fa-star text-warning"></i>
                          <i class="fa-solid fa-star text-warning"></i>
                          <i class="fa-solid fa-star text-warning"></i>
                          <i class="fa-regular fa-star text-warning"></i>
                        </div>
                      </div>
                      <p class="card-text text-muted small mb-0">Personale gentile e preparato. L'attenzione dedicata ad ogni paziente è davvero apprezzabile. Ci tornerò sicuramente.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Logo responsive */
.logo-responsive {
  height: 2.5rem;
}

@media (min-width: 768px) {
  .logo-responsive {
    height: 3rem;
  }
}

/* Titoli responsive */
.responsive-title {
  font-size: 1.75rem;
}

@media (min-width: 576px) {
  .responsive-title {
    font-size: 2.25rem;
  }
}

@media (min-width: 768px) {
  .responsive-title {
    font-size: 2.5rem;
  }
}

.responsive-lead {
  font-size: 1rem;
}

@media (min-width: 768px) {
  .responsive-lead {
    font-size: 1.125rem;
  }
}

@media (min-width: 992px) {
  .responsive-lead {
    font-size: 1.25rem;
  }
}

/* Hero Section */
.hero-section {
  background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('@/assets/images/sfondo-hero-idm.jpg') no-repeat center center;
  background-size: cover;
  color: white;
}

/* Gallery */
.gallery-card img {
  height: 150px;
  width: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.gallery-card img:hover {
  transform: scale(1.05);
}

@media (min-width: 768px) {
  .gallery-card img {
    height: 200px;
  }
}

/* Staff Card */
.staff-card {
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.staff-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.staff-img {
  width: 80px;
  height: 80px;
  object-fit: cover;
  border: 3px solid #fff;
  box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
}

@media (min-width: 768px) {
  .staff-img {
    width: 100px;
    height: 100px;
  }
}

/* Review Card */
.review-card {
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.review-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1) !important;
}

.stars i {
  font-size: 0.9rem;
}
</style>
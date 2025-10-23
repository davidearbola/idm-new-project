<template>
  <div class="container-fluid py-4">
    <div class="row mb-4">
      <div class="col">
        <h2>Gestione Disponibilità</h2>
        <p class="text-muted">Configura i tuoi orari di disponibilità settimanali</p>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary" @click="mostraModalNuova">
          <i class="fas fa-plus-circle me-2"></i>Aggiungi Disponibilità
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Caricamento...</span>
      </div>
    </div>

    <div v-else>
      <div class="card shadow-sm">
        <div class="card-body">
          <div v-if="disponibilita.length === 0" class="text-center py-5">
            <i class="fas fa-calendar-times display-1 text-muted"></i>
            <p class="mt-3 text-muted">Nessuna disponibilità configurata</p>
            <button class="btn btn-primary" @click="mostraModalNuova">
              Aggiungi la prima disponibilità
            </button>
          </div>

          <div v-else>
            <div v-for="giorno in giorniConDisponibilita" :key="giorno.numero" class="mb-4">
              <h5 class="border-bottom pb-2">{{ giorno.nome }}</h5>
              <div class="row g-3">
                <div v-for="disp in giorno.disponibilita" :key="disp.id" class="col-md-6 col-lg-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                          <h6 class="mb-1">
                            <i class="fas fa-clock me-2"></i>
                            {{ disp.start_time }} - {{ disp.end_time }}
                          </h6>
                          <small class="text-muted">Slot: {{ disp.intervallo_slot }} min</small>
                        </div>
                        <span :class="['badge', disp.is_active ? 'bg-success' : 'bg-secondary']">
                          {{ disp.is_active ? 'Attiva' : 'Disattivata' }}
                        </span>
                      </div>
                      <p class="mb-2">
                        <i class="fas fa-users me-2"></i>
                        {{ disp.poltrone_disponibili }}
                        {{ disp.poltrone_disponibili === 1 ? 'poltrona' : 'poltrone' }}
                      </p>
                      <div class="btn-group w-100" role="group">
                        <button
                          class="btn btn-sm btn-outline-primary"
                          @click="modificaDisponibilita(disp)"
                        >
                          <i class="fas fa-edit"></i>
                        </button>
                        <button
                          class="btn btn-sm btn-outline-danger"
                          @click="confermaEliminazione(disp)"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
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

    <!-- Modal Aggiungi/Modifica -->
    <div class="modal fade" ref="modalDisponibilita" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              {{ modalMode === 'create' ? 'Nuova Disponibilità' : 'Modifica Disponibilità' }}
            </h5>
            <button type="button" class="btn-close" @click="chiudiModal"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="salvaDisponibilita">
              <div class="mb-3">
                <label class="form-label">Giorno della Settimana</label>
                <select v-model="form.giorno_settimana" class="form-select" required>
                  <option value="">Seleziona un giorno</option>
                  <option v-for="giorno in giorniSettimana" :key="giorno.value" :value="giorno.value">
                    {{ giorno.label }}
                  </option>
                </select>
              </div>

              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Ora Inizio</label>
                  <input v-model="form.start_time" type="time" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Ora Fine</label>
                  <input v-model="form.end_time" type="time" class="form-control" required>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Durata Slot (minuti)</label>
                <select v-model.number="form.intervallo_slot" class="form-select" required>
                  <option :value="15">15 minuti</option>
                  <option :value="30">30 minuti</option>
                  <option :value="45">45 minuti</option>
                  <option :value="60">60 minuti</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Numero Poltrone Disponibili</label>
                <input 
                  v-model.number="form.poltrone_disponibili" 
                  type="number" 
                  min="1" 
                  max="10"
                  class="form-control" 
                  required
                >
                <small class="form-text text-muted">
                  Numero di pazienti che possono essere visitati contemporaneamente
                </small>
              </div>

              <div class="mb-3" v-if="modalMode === 'edit'">
                <div class="form-check form-switch">
                  <input 
                    v-model="form.is_active" 
                    class="form-check-input" 
                    type="checkbox" 
                    id="isActive"
                  >
                  <label class="form-check-label" for="isActive">
                    Disponibilità attiva
                  </label>
                </div>
              </div>

              <div v-if="formErrors" class="alert alert-danger">
                <ul class="mb-0">
                  <li v-for="(error, field) in formErrors" :key="field">
                    {{ error[0] }}
                  </li>
                </ul>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="chiudiModal">
              Annulla
            </button>
            <button 
              type="button" 
              class="btn btn-primary" 
              @click="salvaDisponibilita"
              :disabled="isLoading"
            >
              <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
              {{ modalMode === 'create' ? 'Crea' : 'Aggiorna' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useDisponibilitaStore } from '@/stores/disponibilitaStore'
import { useToast } from 'vue-toastification'
import { Modal } from 'bootstrap'

const disponibilitaStore = useDisponibilitaStore()
const { isLoading, disponibilita, giorniSettimana } = storeToRefs(disponibilitaStore)
const toast = useToast()

const modalDisponibilita = ref(null)
let modalInstance = null
const modalMode = ref('create')
const form = ref({
  id: null,
  giorno_settimana: '',
  start_time: '',
  end_time: '',
  intervallo_slot: 30,
  poltrone_disponibili: 1,
  is_active: true,
})
const formErrors = ref(null)

const giorniConDisponibilita = computed(() => {
  const grouped = {}
  disponibilita.value.forEach(disp => {
    if (!grouped[disp.giorno_settimana]) {
      grouped[disp.giorno_settimana] = {
        numero: disp.giorno_settimana,
        nome: disponibilitaStore.getNomeGiorno(disp.giorno_settimana),
        disponibilita: []
      }
    }
    grouped[disp.giorno_settimana].disponibilita.push(disp)
  })
  
  return Object.values(grouped).sort((a, b) => a.numero - b.numero)
})

onMounted(async () => {
  modalInstance = new Modal(modalDisponibilita.value)
  await disponibilitaStore.fetchDisponibilita()
})

function mostraModalNuova() {
  modalMode.value = 'create'
  form.value = {
    id: null,
    giorno_settimana: '',
    start_time: '',
    end_time: '',
    intervallo_slot: 30,
    poltrone_disponibili: 1,
    is_active: true,
  }
  formErrors.value = null
  modalInstance.show()
}

function modificaDisponibilita(disp) {
  modalMode.value = 'edit'
  form.value = {
    id: disp.id,
    giorno_settimana: disp.giorno_settimana,
    start_time: disp.start_time,
    end_time: disp.end_time,
    intervallo_slot: disp.intervallo_slot,
    poltrone_disponibili: disp.poltrone_disponibili,
    is_active: disp.is_active,
  }
  formErrors.value = null
  modalInstance.show()
}

async function salvaDisponibilita() {
  formErrors.value = null
  
  const data = {
    giorno_settimana: form.value.giorno_settimana,
    start_time: form.value.start_time,
    end_time: form.value.end_time,
    intervallo_slot: form.value.intervallo_slot,
    poltrone_disponibili: form.value.poltrone_disponibili,
    is_active: form.value.is_active,
  }

  let result
  if (modalMode.value === 'create') {
    result = await disponibilitaStore.creaDisponibilita(data)
  } else {
    result = await disponibilitaStore.aggiornaDisponibilita(form.value.id, data)
  }

  if (result.success) {
    toast.success(result.message)
    chiudiModal()
  } else {
    if (result.errors) {
      formErrors.value = result.errors
    } else {
      toast.error(result.message)
    }
  }
}

async function confermaEliminazione(disp) {
  if (confirm(`Sei sicuro di voler eliminare questa disponibilità del ${disponibilitaStore.getNomeGiorno(disp.giorno_settimana)}?`)) {
    const result = await disponibilitaStore.eliminaDisponibilita(disp.id)
    if (result.success) {
      toast.success(result.message)
    } else {
      toast.error(result.message)
    }
  }
}

function chiudiModal() {
  modalInstance.hide()
}
</script>

<style scoped>
.card {
  transition: transform 0.2s;
}

.card:hover {
  transform: translateY(-2px);
}
</style>
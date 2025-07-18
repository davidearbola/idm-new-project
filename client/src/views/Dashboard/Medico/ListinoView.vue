<script setup>
import { ref, reactive, onMounted, watch, onUnmounted } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { useMedicoStore } from '@/stores/medicoStore';
import { useUiStore } from '@/stores/uiStore';
import { storeToRefs } from 'pinia';
import { useToast } from 'vue-toastification';
import * as yup from 'yup';
import { Form, Field, ErrorMessage } from 'vee-validate';

// --- STORES E UTILITY ---
const medicoStore = useMedicoStore();
const uiStore = useUiStore();
const { listino: listinoFromServer, isLoading } = storeToRefs(medicoStore);
const toast = useToast();

// --- STATO LOCALE ---
const listinoLocale = ref([]);
const initialState = ref('');
const nuovaVoce = reactive({ nome: '', descrizione: '', prezzo: null });

// --- NUOVA LOGICA PER INLINE EDITING ---
const editingItemId = ref(null);
let originalItemData = null; // Per il backup dei dati durante la modifica

const startEditing = (item) => {
  // Salva una copia dei dati originali prima di iniziare a modificare
  originalItemData = { ...item };
  editingItemId.value = item.id;
};

const cancelEditing = (item) => {
  // Ripristina i dati originali
  const index = listinoLocale.value.findIndex(i => i.id === item.id);
  if (index !== -1) {
    listinoLocale.value[index] = originalItemData;
  }
  editingItemId.value = null;
  originalItemData = null;
};

const handleUpdateCustomItem = async (item) => {
    const { success, message } = await medicoStore.updateCustomItem(item);
    if (success) {
        toast.success(message);
        // La lista si aggiornerà automaticamente grazie al fetchListino nello store
        listinoLocale.value = JSON.parse(JSON.stringify(listinoFromServer.value));
        initialState.value = JSON.stringify(listinoLocale.value);
        editingItemId.value = null; // Esce dalla modalità modifica
    } else {
        toast.error(message);
    }
};

const handleDeleteCustomItem = async (item) => {
    if (window.confirm(`Sei sicuro di voler eliminare la voce "${item.nome}"?`)) {
        const { success, message } = await medicoStore.deleteCustomItem(item.id);
        if(success) {
            toast.success(message);
            const index = listinoLocale.value.findIndex(i => i.id === item.id && i.tipo === 'custom');
            if (index !== -1) {
                listinoLocale.value.splice(index, 1);
            }
            initialState.value = JSON.stringify(listinoLocale.value);
        } else {
            toast.error(message);
        }
    }
};
// --- FINE LOGICA INLINE EDITING ---

const schemaNuovaVoce = yup.object({
  nome: yup.string().required('Il nome è obbligatorio'),
  prezzo: yup.number().typeError('Deve essere un numero').required('Il prezzo è obbligatorio').min(0),
});

// --- GESTIONE DELLO STATO E DEI SALVATAGGI (con uiStore) ---
onMounted(async () => {
  await medicoStore.fetchListino();
  listinoLocale.value = JSON.parse(JSON.stringify(listinoFromServer.value));
  initialState.value = JSON.stringify(listinoLocale.value);
  uiStore.clearUnsavedChanges();
  window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
  uiStore.clearUnsavedChanges();
  window.removeEventListener('beforeunload', handleBeforeUnload);
});

watch(listinoLocale, (newValue) => {
  const isChanged = JSON.stringify(newValue) !== initialState.value;
  uiStore.setUnsavedChanges(isChanged);
}, { deep: true });

const aggiungiVoceLocalmente = (values, { resetForm }) => {
  listinoLocale.value.push({
    id: `temp-${Date.now()}`, 
    nome: values.nome,
    descrizione: values.descrizione || '',
    prezzo: values.prezzo,
    tipo: 'custom',
    is_active: true,
    isNew: true, 
  });
  resetForm();
};

const handleSalvaListino = async () => {
  const payload = { masterItems: [], customItems: [] };
  const initialDataMap = new Map(JSON.parse(initialState.value).map(item => [item.id, item]));

  listinoLocale.value.forEach(item => {
    if (item.isNew) {
      payload.customItems.push({ nome: item.nome, descrizione: item.descrizione, prezzo: item.prezzo });
    } else if (item.tipo === 'master') {
      const initialItem = initialDataMap.get(item.id);
      if (item.prezzo !== initialItem.prezzo || item.is_active !== initialItem.is_active) {
        payload.masterItems.push({ id: item.id, prezzo: item.prezzo, is_active: item.is_active });
      }
    }
  });

  const { success, message } = await medicoStore.saveListino(payload);
  if (success) {
    toast.success(message);
    listinoLocale.value = JSON.parse(JSON.stringify(listinoFromServer.value));
    initialState.value = JSON.stringify(listinoLocale.value);
    uiStore.clearUnsavedChanges();
  } else {
    toast.error(message);
  }
};

// --- GESTIONE MODIFICHE NON SALVATE (con uiStore) ---
onBeforeRouteLeave((to, from, next) => {
  if (uiStore.hasUnsavedChanges) {
    if (window.confirm('Hai delle modifiche non salvate. Sei sicuro di voler lasciare la pagina?')) {
      next();
    } else {
      next(false);
    }
  } else {
    next();
  }
});

const handleBeforeUnload = (event) => {
  if (uiStore.hasUnsavedChanges) {
    event.preventDefault();
    event.returnValue = ''; 
  }
};
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="display-5 fw-bold">Gestione Listino</h1>
            <p class="lead text-muted">Imposta i prezzi per le prestazioni standard e aggiungi le tue voci personalizzate.</p>
        </div>
        <div>
            <button class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#listinoModal">Mostra Listino</button>
            <button class="btn btn-accent" @click="handleSalvaListino" :disabled="!uiStore.hasUnsavedChanges || isLoading">
                <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                Salva Listino
            </button>
        </div>
    </div>
    <hr class="my-4">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title">Aggiungi Voce Personalizzata</h5>
            <Form @submit="aggiungiVoceLocalmente" :validation-schema="schemaNuovaVoce" v-slot="{ errors }">
            <div class="row align-items-end g-3">
                <div class="col-md-4"><label>Nome Prestazione</label><Field name="nome" type="text" v-model="nuovaVoce.nome" class="form-control" /></div>
                <div class="col-md-4"><label>Descrizione (Opzionale)</label><Field name="descrizione" type="text" v-model="nuovaVoce.descrizione" class="form-control" /></div>
                <div class="col-md-2"><label>Prezzo (€)</label><Field name="prezzo" type="number" step="0.01" v-model="nuovaVoce.prezzo" class="form-control" /></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Aggiungi alla Tabella</button></div>
            </div>
            <ErrorMessage name="nome" class="text-danger small d-block" />
            <ErrorMessage name="prezzo" class="text-danger small d-block" />
            </Form>
        </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header"><h5 class="mb-0">Elenco Prestazioni</h5></div>
      <div class="table-responsive">
        <table class="table table-hover table-vcenter mb-0">
          <thead>
            <tr>
              <th style="width: 5%;">Attiva</th>
              <th>Prestazione</th>
              <th style="width: 20%;">Prezzo (€)</th>
              <th style="width: 15%;">Tipo</th>
              <th style="width: 10%;">Azioni</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in listinoLocale" :key="item.id">
              <td>
                <div class="form-check form-switch" v-if="item.tipo === 'master'"><input class="form-check-input" type="checkbox" v-model="item.is_active"></div>
              </td>
              <td>
                <div v-if="editingItemId === item.id">
                    <input type="text" class="form-control form-control-sm mb-1" v-model="item.nome">
                    <input type="text" class="form-control form-control-sm" v-model="item.descrizione" placeholder="Descrizione">
                </div>
                <div v-else>
                    <p class="fw-bold mb-0">{{ item.nome }}</p>
                    <small class="text-muted" v-if="item.descrizione">{{ item.descrizione }}</small>
                </div>
              </td>
              <td>
                <div v-if="editingItemId === item.id">
                    <input type="number" step="0.01" class="form-control form-control-sm" v-model.number="item.prezzo">
                </div>
                <div v-else>
                    <input type="number" step="0.01" class="form-control" v-model.number="item.prezzo" :disabled="item.tipo === 'custom' && !item.isNew">
                </div>
              </td>
              <td>
                <span v-if="item.tipo === 'custom'" class="badge bg-accent-soft text-accent">Personalizzata</span>
                <span v-else class="badge bg-secondary-soft text-secondary">Globale</span>
              </td>
              <td>
                <div v-if="item.tipo === 'custom' && !item.isNew">
                    <div v-if="editingItemId === item.id" class="btn-group btn-group-sm">
                        <button class="btn btn-success" @click="handleUpdateCustomItem(item)" title="Salva"><i class="fa-solid fa-check"></i></button>
                        <button class="btn btn-secondary" @click="cancelEditing(item)" title="Annulla"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div v-else class="btn-group btn-group-sm">
                        <button class="btn btn-light" @click="startEditing(item)" title="Modifica"><i class="fa-solid fa-pencil"></i></button>
                        <button class="btn btn-light text-danger" @click="handleDeleteCustomItem(item)" title="Elimina"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
              </td>
            </tr>
            <tr v-if="listinoLocale.length === 0"><td colspan="5" class="text-center text-muted py-4">Nessuna voce nel listino.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    
    <Teleport to="body">
      <div class="modal fade" id="listinoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Anteprima Listino Medico</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <ul class="list-group">
                  <li v-for="item in listinoLocale.filter(i => i.is_active && i.prezzo > 0)" :key="item.id" class="list-group-item d-flex justify-content-between align-items-center">
                      {{ item.nome }}
                      <span class="fw-bold">€ {{ item.prezzo }}</span>
                  </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
table-vcenter td, .table-vcenter th {
vertical-align: middle;
}
.badge.bg-accent-soft {
background-color: rgba(var(--bs-accent-rgb), 0.1);
}
.badge.bg-secondary-soft {
background-color: var(--bs-light);
}
</style>
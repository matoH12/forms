<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { MiniMap } from '@vue-flow/minimap';
import StartNode from './nodes/StartNode.vue';
import EndNode from './nodes/EndNode.vue';
import ApiCallNode from './nodes/ApiCallNode.vue';
import ApprovalNode from './nodes/ApprovalNode.vue';
import ConditionNode from './nodes/ConditionNode.vue';
import EmailNode from './nodes/EmailNode.vue';
import DelayNode from './nodes/DelayNode.vue';
import NodeEditor from './NodeEditor.vue';

const props = defineProps({
    initialNodes: { type: Array, default: () => [] },
    initialEdges: { type: Array, default: () => [] },
    formFields: { type: Array, default: () => [] },
    emailTemplates: { type: Array, default: () => [] },
});

// Make formFields and emailTemplates accessible in template
const formFieldsRef = computed(() => props.formFields);
const emailTemplatesRef = computed(() => props.emailTemplates);

const emit = defineEmits(['update']);

const nodes = ref([...props.initialNodes]);
const edges = ref([...props.initialEdges]);
const editingNode = ref(null);

const nodeTypes = {
    start: StartNode,
    end: EndNode,
    api_call: ApiCallNode,
    approval: ApprovalNode,
    condition: ConditionNode,
    email: EmailNode,
    delay: DelayNode,
};

const { onConnect, addEdges, onNodeDragStop, onNodesChange, onEdgesChange } = useVueFlow();

onConnect((connection) => {
    addEdges([connection]);
});

onNodeDragStop(() => {
    emitUpdate();
});

onNodesChange(() => {
    emitUpdate();
});

onEdgesChange(() => {
    emitUpdate();
});

const emitUpdate = () => {
    // Clean nodes - remove VueFlow internal properties
    const cleanNodes = nodes.value.map(node => ({
        id: node.id,
        type: node.type,
        position: { x: node.position.x, y: node.position.y },
        data: node.data,
    }));

    // Clean edges - remove VueFlow internal properties
    const cleanEdges = edges.value.map(edge => ({
        id: edge.id,
        source: edge.source,
        target: edge.target,
        sourceHandle: edge.sourceHandle || null,
        targetHandle: edge.targetHandle || null,
    }));

    emit('update', { nodes: cleanNodes, edges: cleanEdges });
};

const availableNodeTypes = [
    { type: 'api_call', label: 'API volanie', icon: '🌐' },
    { type: 'approval', label: 'Schválenie', icon: '✅' },
    { type: 'condition', label: 'Podmienka', icon: '❓' },
    { type: 'email', label: 'Email', icon: '📧' },
    { type: 'delay', label: 'Čakanie', icon: '⏱️' },
];

const addNode = (type) => {
    const id = `node_${Date.now()}`;
    const newNode = {
        id,
        type,
        position: { x: 250, y: 200 + (nodes.value.length * 50) },
        data: {
            label: availableNodeTypes.find(n => n.type === type)?.label || type,
            ...getDefaultData(type),
        },
    };
    nodes.value = [...nodes.value, newNode];
    emitUpdate();
    editNode(newNode);
};

const getDefaultData = (type) => {
    switch (type) {
        case 'api_call':
            return { method: 'GET', url: '', headers: {}, body: '' };
        case 'approval':
            return { approver_email: '', message: '' };
        case 'condition':
            return { field: '', operator: 'equals', value: '' };
        case 'email':
            return { template_id: null, recipient_type: 'submitter', to: '{{user.email}}' };
        case 'delay':
            return { delay_seconds: 5 };
        default:
            return {};
    }
};

const onNodeClick = (event) => {
    const node = event.node || event;
    if (node && node.type && !['start', 'end'].includes(node.type)) {
        editNode(node);
    }
};

const editNode = (node) => {
    // Find the node in our nodes array (may have better structure from VueFlow)
    const foundNode = nodes.value.find(n => n.id === node.id);
    const sourceNode = foundNode || node;

    editingNode.value = {
        ...sourceNode,
        type: sourceNode.type,
        data: { ...(sourceNode.data || {}) },
    };
};

const saveNode = () => {
    if (editingNode.value) {
        nodes.value = nodes.value.map(n =>
            n.id === editingNode.value.id
                ? { ...n, data: editingNode.value.data }
                : n
        );
        editingNode.value = null;
        emitUpdate();
    }
};

const deleteNode = (nodeId) => {
    if (confirm('Naozaj chcete odstrániť tento krok?')) {
        nodes.value = nodes.value.filter(n => n.id !== nodeId);
        edges.value = edges.value.filter(e => e.source !== nodeId && e.target !== nodeId);
        editingNode.value = null;
        emitUpdate();
    }
};

watch(() => props.initialNodes, (newNodes) => {
    nodes.value = [...newNodes];
}, { deep: true });

watch(() => props.initialEdges, (newEdges) => {
    edges.value = [...newEdges];
}, { deep: true });
</script>

<template>
    <div class="flex h-full">
        <!-- Sidebar with node types -->
        <div class="w-48 bg-gray-100 dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 p-3 overflow-y-auto">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Pridať krok:</p>
            <div class="space-y-2">
                <button
                    v-for="nodeType in availableNodeTypes"
                    :key="nodeType.type"
                    @click="addNode(nodeType.type)"
                    class="w-full flex items-center gap-2 px-3 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-600 text-sm text-left text-gray-900 dark:text-white"
                >
                    <span>{{ nodeType.icon }}</span>
                    <span>{{ nodeType.label }}</span>
                </button>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Tipy:
                </p>
                <ul class="text-xs text-gray-500 dark:text-gray-400 mt-1 space-y-1 list-disc list-inside">
                    <li>Ťahajte uzly pre presun</li>
                    <li>Kliknite na uzol pre úpravu</li>
                    <li>Prepojte uzly ťahaním</li>
                </ul>
            </div>
        </div>

        <!-- Vue Flow canvas -->
        <div class="flex-1 workflow-canvas">
            <VueFlow
                v-model:nodes="nodes"
                v-model:edges="edges"
                :node-types="nodeTypes"
                :default-edge-options="{ type: 'smoothstep', animated: true }"
                @node-click="onNodeClick"
                class="bg-gray-50 dark:bg-gray-900"
                fit-view-on-init
            >
                <Background pattern-color="currentColor" class="text-gray-300 dark:text-gray-600" :gap="20" />
                <Controls />
                <MiniMap />
            </VueFlow>
        </div>

        <!-- Node editor modal -->
        <div v-if="editingNode" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Upraviť: {{ editingNode.data?.label }}</h3>
                    <button @click="editingNode = null" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-4 max-h-[60vh] overflow-y-auto">
                    <NodeEditor v-model="editingNode.data" :type="editingNode.type" :form-fields="formFieldsRef" :email-templates="emailTemplatesRef" />
                </div>

                <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-between">
                    <button @click="deleteNode(editingNode.id)" class="btn btn-danger">
                        Odstrániť
                    </button>
                    <div class="flex gap-2">
                        <button @click="editingNode = null" class="btn btn-secondary">Zrušiť</button>
                        <button @click="saveNode" class="btn btn-primary">Uložiť</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
@import '@vue-flow/controls/dist/style.css';
@import '@vue-flow/minimap/dist/style.css';

/* MiniMap - Light mode improvements */
.vue-flow__minimap {
    background-color: #ffffff;
    border: 2px solid #d1d5db;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.vue-flow__minimap-mask {
    fill: rgba(59, 130, 246, 0.15);
    stroke: #3b82f6;
    stroke-width: 2;
}

.vue-flow__minimap-node {
    fill: #3b82f6;
    stroke: #1d4ed8;
    stroke-width: 1;
}

/* Dark mode for MiniMap */
.dark .vue-flow__minimap {
    background-color: #1f2937;
    border-color: #4b5563;
}

.dark .vue-flow__minimap-mask {
    fill: rgba(96, 165, 250, 0.2);
    stroke: #60a5fa;
    stroke-width: 2;
}

.dark .vue-flow__minimap-node {
    fill: #60a5fa;
    stroke: #93c5fd;
    stroke-width: 1;
}

/* Controls - Light mode improvements */
.vue-flow__controls {
    background-color: #ffffff;
    border: 2px solid #d1d5db;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.vue-flow__controls-button {
    background-color: #ffffff;
    border-color: #e5e7eb;
    fill: #374151;
}

.vue-flow__controls-button:hover {
    background-color: #f3f4f6;
}

.vue-flow__controls-button svg {
    fill: #374151;
}

/* Dark mode for Controls */
.dark .vue-flow__controls {
    background-color: #374151;
    border-color: #4b5563;
}

.dark .vue-flow__controls-button {
    background-color: #374151;
    border-color: #4b5563;
    fill: #d1d5db;
}

.dark .vue-flow__controls-button:hover {
    background-color: #4b5563;
}

.dark .vue-flow__controls-button svg {
    fill: #d1d5db;
}

/* Edge labels */
.vue-flow__edge-text {
    fill: #374151;
}

.dark .vue-flow__edge-text {
    fill: #e5e7eb;
}

/* Edge paths */
.vue-flow__edge-path {
    stroke: #6b7280;
}

.dark .vue-flow__edge-path {
    stroke: #9ca3af;
}
</style>

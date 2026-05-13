<script setup lang="ts">
import { router } from "@inertiajs/vue3";

interface AccountTreeNode {
    id: number;
    membership_id: number | null;
    membership_number: string | null;
    holder_name: string;
    membership_type_name: string | null;
    status: string;
    separation_reason: string | null;
    derived?: AccountTreeNode[];
}

const props = defineProps<{ node: AccountTreeNode; depth?: number }>();
const depth = props.depth ?? 0;

const statusLabel = (status: string) => {
    const map: Record<string, string> = {
        active: "Activa",
        suspended: "Suspendida",
        cancelled: "Cancelada",
    };
    return map[status] ?? status;
};

const statusColor = (status: string) => {
    const map: Record<string, string> = {
        active: "success",
        suspended: "warning",
        cancelled: "default",
    };
    return map[status] ?? "default";
};
</script>

<template>
    <div :style="depth > 0 ? `margin-left: ${depth * 20}px` : ''">
        <v-card
            variant="outlined"
            class="pa-3 cursor-pointer"
            :class="{ 'mb-1': true }"
            @click="node.membership_id && router.visit(route('members.manage.show', node.membership_id))"
        >
            <div class="d-flex align-center gap-2">
                <v-icon size="small" color="medium-emphasis">
                    {{ depth === 0 ? 'mdi-account-arrow-right' : 'mdi-subdirectory-arrow-right' }}
                </v-icon>
                <div class="flex-grow-1">
                    <span class="font-weight-medium">#{{ node.membership_number }}</span>
                    — {{ node.holder_name }}
                    <span v-if="node.membership_type_name" class="text-medium-emphasis">
                        ({{ node.membership_type_name }})
                    </span>
                    <div v-if="node.separation_reason" class="text-caption text-medium-emphasis mt-0.5">
                        {{ node.separation_reason }}
                    </div>
                </div>
                <v-chip size="x-small" :color="statusColor(node.status)">
                    {{ statusLabel(node.status) }}
                </v-chip>
            </div>
        </v-card>

        <template v-if="node.derived && node.derived.length">
            <AccountTreeNode
                v-for="child in node.derived"
                :key="child.id"
                :node="child"
                :depth="depth + 1"
                class="mt-1"
            />
        </template>
    </div>
</template>

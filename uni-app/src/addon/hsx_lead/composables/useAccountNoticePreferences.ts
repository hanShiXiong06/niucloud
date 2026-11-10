import { computed, ref } from 'vue';
import { getSiteId, getToken } from '@/utils/common';
import { getAccountNoticePreferences, saveAccountNoticePreferences, reportAccountNoticeAuthorization } from '../api/notice';
import { coopError } from '../api/cooperation';
type NoticeEvent = {
    event_key: string;
    name: string;
    channels: {
        channel: string;
        enabled: boolean;
        configured: boolean;
        authorization_required: boolean;
        template_id?: string;
    }[];
};
const channelKeys = new Set(['weapp', 'wechat', 'sms']);
export function parseNoticePreferences(value: any): NoticeEvent[] {
    if (!value || !Array.isArray(value.events) || !value.events.length || value.events.length > 32)
        throw Error('通知偏好未完整返回，请重新读取');
    const seen = new Set<string>();
    return value.events.map((row: any) => {
        if (!row || typeof row.event_key !== 'string' || !/^[a-z][a-z0-9_]{0,63}$/.test(row.event_key) || seen.has(row.event_key) || typeof row.name !== 'string' || !row.name.trim() || !Array.isArray(row.channels) || row.channels.length !== channelKeys.size)
            throw Error('通知场景未完整返回，请重新读取');
        seen.add(row.event_key);
        const channels = new Set<string>();
        for (const channel of row.channels) {
            if (!channel || !channelKeys.has(channel.channel) || channels.has(channel.channel) || typeof channel.enabled !== 'boolean' || typeof channel.configured !== 'boolean' || typeof channel.authorization_required !== 'boolean' || (channel.template_id !== undefined && typeof channel.template_id !== 'string'))
                throw Error('通知渠道未完整返回，请重新读取');
            channels.add(channel.channel);
        }
        return JSON.parse(JSON.stringify(row));
    });
}
/** Preference changes and native WeChat consent are separate, identity-scoped operations. */
export function useAccountNoticePreferences() {
    const events = ref<NoticeEvent[]>([]), loading = ref(false), ready = ref(false), error = ref(''), saving = ref(false), saveError = ref(''), readRequired = ref(false), requesting = ref(false), subscriptionMessage = ref(''), supported = ref(false), loggedIn = ref(!!getToken());
    // #ifdef MP-WEIXIN
    supported.value = true;
    // #endif
    let actor = '', generation = 0, active = true, disposed = false, bulkOffset = 0;
    const identity = () => `${getSiteId(import.meta.env.VITE_SITE_ID || uni.getStorageSync('wap_site_id') || '') || ''}:${getToken() || ''}`;
    const current = (who: string, turn: number) => active && !disposed && !!getToken() && who === actor && who === identity() && turn === generation;
    function identify() {
        const next = identity();
        loggedIn.value = !!getToken();
        if (next !== actor) {
            actor = next;
            generation++;
            events.value = [];
            ready.value = false;
            error.value = '';
            saveError.value = '';
            readRequired.value = false;
            loading.value = false;
            saving.value = false;
            requesting.value = false;
            subscriptionMessage.value = '';
            bulkOffset = 0;
        }
        return actor;
    }
    const eligible = computed(() => events.value.flatMap(event => event.channels.filter(channel => channel.channel === 'weapp' && channel.authorization_required && channel.enabled && channel.configured && typeof channel.template_id === 'string' && channel.template_id.trim()).map(channel => ({ event_key: event.event_key, template_id: channel.template_id!.trim() }))));
    const canSubscribe = computed(() => supported.value && ready.value && eligible.value.length > 0 && !loading.value && !saving.value && !readRequired.value && !requesting.value);
    const canSubscribeEvent = (eventKey:string) => canSubscribe.value && eligible.value.some(item=>item.event_key===eventKey);
    async function load() {
        const who = identify();
        if (saving.value || requesting.value)
            return false;
        const turn = ++generation;
        if (!getToken() || !active || disposed)
            return false;
        loading.value = true;
        error.value = '';
        try {
            const response: any = await getAccountNoticePreferences();
            if (!current(who, turn))
                return false;
            events.value = parseNoticePreferences(response?.data);
            ready.value = true;
            saveError.value = '';
            readRequired.value = false;
            return true;
        }
        catch (cause) {
            if (current(who, turn)) {
                events.value = [];
                ready.value = false;
                error.value = coopError(cause, '通知偏好读取失败，请重新加载');
            }
            ;
            return false;
        }
        finally {
            if (turn === generation)
                loading.value = false;
        }
    }
    async function change(event_key: string, channel: string, enabled: boolean) {
        const who = identify(), turn = generation, item = events.value.find(event => event.event_key === event_key)?.channels.find(row => row.channel === channel);
        if (!item || typeof enabled !== 'boolean' || !current(who, turn) || !ready.value || loading.value || saving.value || readRequired.value || requesting.value || item.enabled === enabled)
            return false;
        const previous = item.enabled;
        item.enabled = enabled;
        saving.value = true;
        saveError.value = '';
        subscriptionMessage.value = '';
        try {
            const response: any = await saveAccountNoticePreferences([{ event_key, channel, enabled }]);
            if (!current(who, turn))
                return false;
            events.value = parseNoticePreferences(response?.data);
            return true;
        }
        catch (cause) {
            if (current(who, turn)) {
                item.enabled = previous;
                readRequired.value = true;
                saveError.value = coopError(cause, '偏好保存结果待核对，请重新读取后确认');
            }
            ;
            return false;
        }
        finally {
            if (turn === generation)
                saving.value = false;
        }
    }
    function subscribe(eventKey?: string) {
        const who = identify(), turn = generation;
        if (!supported.value) {
            subscriptionMessage.value = '请在微信小程序内确认订阅；本页开关仅设置提醒偏好。';
            return;
        }
        if (!canSubscribe.value || !current(who, turn))
            return;
        const specific = typeof eventKey === 'string' && eventKey !== '';
        const all = [...eligible.value], offset = all.length ? bulkOffset % all.length : 0;
        const selected = (specific ? all.filter(item=>item.event_key===eventKey) : [...all.slice(offset),...all.slice(0,offset)]).slice(0,3);
        const ids = [...new Set(selected.map(item => item.template_id))];
        if (!ids.length)
            return;
        requesting.value = true;
        if (!specific && all.length) bulkOffset = (offset + selected.length) % all.length;
        subscriptionMessage.value = '';
        const finish = (message: string) => {
            if (current(who, turn)) {
                requesting.value = false;
                subscriptionMessage.value = message;
            }
        };
        try {
            // Called synchronously from the user's tap; never await a network read before opening this native dialog.
            uni.requestSubscribeMessage({ tmplIds: ids, success: async (result) => {
                    if (!current(who, turn))
                        return;
                    const accepted = ids.filter(id => (result as Record<string, any>)[id] === 'accept');
                    if (!accepted.length) {
                        finish(ids.some(id => ['reject', 'ban'].includes((result as Record<string, any>)[id])) ? '本次未同意订阅，其他功能不受影响。' : '订阅尚未完成，可在需要时再次点击。');
                        return;
                    }
                    const keys = [...new Set(selected.filter(item => accepted.includes(item.template_id)).map(item => item.event_key))];
                    try {
                        await reportAccountNoticeAuthorization(keys);
                        finish(`已同意本次 ${keys.length} 项提醒；实际送达以微信授权与渠道结果为准。`);
                    }
                    catch (_) {
                        finish('已同意本次订阅，提醒状态暂未核实；请稍后重新进入查看，不会反复弹出授权。');
                    }
                }, fail: result => finish(/cancel|取消/i.test(String(result?.errMsg || '')) ? '本次订阅已取消，其他功能不受影响。' : '订阅暂未完成，请稍后主动重试。') });
        }
        catch (_) {
            finish('订阅暂未完成，请稍后主动重试。');
        }
    }
    function resume() {
        active = true;
        identify();
    }
    function pause() {
        active = false;
        generation++;
        loading.value = false;
        saving.value = false;
        requesting.value = false;
    }
    function dispose() {
        pause();
        disposed = true;
        events.value = [];
        ready.value = false;
    }
    return { events, loading, ready, error, saving, saveError, readRequired, requesting, subscriptionMessage, supported, loggedIn, eligible, canSubscribe, canSubscribeEvent, load, change, subscribe, resume, pause, dispose };
}

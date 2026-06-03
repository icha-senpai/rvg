const { ChannelType, EmbedBuilder } = require('discord.js');
const api = require('../utils/api');

const ACCEPT_EMOJI = process.env.PROMOTION_ACCEPT_EMOJI || '✅';
const RANK_EMBED_COLORS = {
    member: 0x40b2ff,
    cit: 0xd1ac18,
    commander: 0xf1c40f,
    wing_commander: 0xf58925,
    admiral: 0xfa530b,
    grand_admiral: 0xba0202,
    tech_director: 0x71368a,
    director: 0xff1a00,
};

function normalizeRankSlug(value) {
    return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[\s-]+/g, '_');
}

function resolveEmbedColor(payload) {
    const rankSlug = normalizeRankSlug(payload.to_rank || payload.rank || payload.to_rank_label);
    return RANK_EMBED_COLORS[rankSlug] || 0x3ba4ff;
}

function buildOfferEmbed(payload) {
    return new EmbedBuilder()
        .setColor(resolveEmbedColor(payload))
        .setTitle(`Promotion Offer: ${payload.to_rank_label}`)
        .setDescription(payload.dm_content)
        .addFields(
            { name: 'Promoter', value: payload.promoter_mention || payload.promoter_name || 'Command', inline: true },
            { name: 'Branch', value: payload.branch_role_label || 'Assigned Branch', inline: true },
            { name: 'Expires', value: payload.expires_at_label || 'Soon', inline: false }
        );
}

function buildQuarterEmbed(payload) {
    return new EmbedBuilder()
        .setColor(resolveEmbedColor(payload))
        .setTitle(`Promotion Offered: ${payload.to_rank_label}`)
        .setDescription(payload.quarter_content)
        .addFields(
            { name: 'Member', value: payload.member_name || 'Member', inline: true },
            { name: 'Promoter', value: payload.promoter_mention || payload.promoter_name || 'Command', inline: true },
            { name: 'Branch', value: payload.branch_role_label || 'Assigned Branch', inline: true },
            { name: 'Expires', value: payload.expires_at_label || 'Soon', inline: false }
        );
}

function buildStatusEmbed(payload, status) {
    const rankLabel = payload.to_rank_label || payload.to_rank || 'Promotion';
    const memberName = payload.member_name || 'Member';
    const branchLabel = payload.branch_role_label || 'assigned branch';
    const styles = {
        accepted: { color: resolveEmbedColor(payload), title: `Promotion Accepted: ${rankLabel}` },
        cancelled: { color: resolveEmbedColor(payload), title: `Promotion Cancelled: ${rankLabel}` },
        expired: { color: resolveEmbedColor(payload), title: `Promotion Expired: ${rankLabel}` },
    };

    const style = styles[status] || { color: resolveEmbedColor(payload), title: `Promotion Update: ${rankLabel}` };
    const description = status === 'accepted'
        ? `${memberName} accepted promotion to ${rankLabel} under ${branchLabel}.`
        : payload.dm_update_content || payload.quarter_update_content || 'Promotion status updated.';

    return new EmbedBuilder()
        .setColor(style.color)
        .setTitle(style.title)
        .setDescription(description);
}

async function fetchQuarterChannel(client, channelId) {
    if (!channelId) {
        throw new Error('quarter channel id missing');
    }

    return client.channels.fetch(channelId);
}

async function fetchMemberGuildRecord(client, discordId) {
    const guild = await client.guilds.fetch(process.env.DISCORD_GUILD_ID);
    return guild.members.fetch(discordId);
}

async function sendPromotionOffer(client, payload) {
    const user = await client.users.fetch(payload.member_discord_id);
    const dmChannel = await user.createDM();
    const quarterChannel = await fetchQuarterChannel(client, payload.quarter_channel_id);

    let dmMessage = null;
    let quarterMessage = null;

    try {
        dmMessage = await dmChannel.send({
            embeds: [buildOfferEmbed(payload)],
        });

        await dmMessage.react(payload.accept_emoji || ACCEPT_EMOJI);

        quarterMessage = await quarterChannel.send({
            embeds: [buildQuarterEmbed(payload)],
        });

        return {
            dmMessageId: dmMessage.id,
            quarterMessageId: quarterMessage.id,
        };
    } catch (error) {
        if (quarterMessage) {
            try { await quarterMessage.delete(); } catch {}
        }

        if (dmMessage) {
            try { await dmMessage.delete(); } catch {}
        }

        throw error;
    }
}

async function upsertDmStatus(user, payload, status) {
    const dmChannel = await user.createDM();
    const embed = buildStatusEmbed(payload, status);

    if (payload.dm_message_id) {
        try {
            const message = await dmChannel.messages.fetch(payload.dm_message_id);
            await message.edit({ embeds: [embed] });
            return;
        } catch {}
    }

    await dmChannel.send({ embeds: [embed] });
}

async function upsertQuarterStatus(client, payload, status) {
    if (!payload.quarter_channel_id) {
        return;
    }

    const quarterChannel = await fetchQuarterChannel(client, payload.quarter_channel_id);
    const embed = buildStatusEmbed(payload, status);

    if (payload.quarter_message_id) {
        try {
            const message = await quarterChannel.messages.fetch(payload.quarter_message_id);
            await message.edit({ embeds: [embed] });
            return;
        } catch {}
    }

    await quarterChannel.send({ embeds: [embed] });
}

async function updatePromotionOfferStatus(client, payload, status) {
    const user = await client.users.fetch(payload.member_discord_id);

    await upsertDmStatus(user, payload, status);
    await upsertQuarterStatus(client, payload, status);
}

async function markPromotionAccepted(client, payload) {
    const user = await client.users.fetch(payload.member_discord_id);
    await upsertDmStatus(user, payload, 'accepted');
    await upsertQuarterStatus(client, payload, 'accepted');
}

async function handleAcceptanceReaction(client, reaction, user) {
    if (user.bot) {
        return;
    }

    if ((reaction.emoji?.name || '') !== ACCEPT_EMOJI) {
        return;
    }

    if (reaction.message?.guildId) {
        return;
    }

    const preparedResponse = await api.preparePromotionAcceptanceByMessage(reaction.message.id, {
        discord_id: user.id,
        message_id: reaction.message.id,
    });

    const prepared = preparedResponse?.data || {};
    if (prepared.already_accepted) {
        return;
    }

    const member = await fetchMemberGuildRecord(client, user.id);

    if (prepared.remove_role_ids?.length) {
        await member.roles.remove(prepared.remove_role_ids, 'Promotion acceptance cleanup');
    }

    if (prepared.add_role_ids?.length) {
        await member.roles.add(prepared.add_role_ids, 'Promotion accepted from website workflow');
    }

    await api.finalizePromotionAcceptanceByMessage(reaction.message.id, {
        discord_id: user.id,
        message_id: reaction.message.id,
    });

    await markPromotionAccepted(client, prepared);
}

module.exports = {
    sendPromotionOffer,
    updatePromotionOfferStatus,
    handleAcceptanceReaction,
};

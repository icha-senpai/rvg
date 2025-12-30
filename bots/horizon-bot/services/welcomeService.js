const {
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
} = require('discord.js');

module.exports = {
  async sendWelcome(member) {
    return;
    const channelId = '1454412966907613227';
    const channel = member.guild.channels.cache.get(channelId);
    if (!channel) return;

    const content = `<@${member.id}>

Welcome to **HORIZON**, *${member.displayName}*!

You have arrived at our **Arrival Platform**. Before receiving full access to our Discord, we must verify your account and membership with HORIZON. Please proceed to **[HORIZON INTERSTELLAR VERIFICATION ](https://horizoninterstellar.com/)** and complete the verification process.

If you have any questions, experiencing any issues verifying your account **or if you are a <@&1454412916148404362>**, please head to https://discord.com/channels/113412259320954880/1454412972842811473`;

    const buttons = new ActionRowBuilder().addComponents(
      new ButtonBuilder()
        .setLabel('Verify')
        .setStyle(ButtonStyle.Link)
        .setURL('https://horizoninterstellar.com/'),

      new ButtonBuilder()
        .setLabel('Need Help?')
        .setStyle(ButtonStyle.Link)
        .setURL(
          'https://discord.com/channels/113412259320954880/1454412972842811473'
        )
    );

    await channel.send({
      content,
      components: [buttons],
      allowedMentions: { users: [member.id] }, // 🔒 prevents accidental mass pings
    });
  },
};

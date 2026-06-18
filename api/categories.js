const { createClient } = require('@supabase/supabase-js');

const supabase = createClient(
    process.env.SUPABASE_URL,
    process.env.SUPABASE_ANON_KEY
);

module.exports = async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('channels')
            .select('category')
            .eq('is_active', true);

        if (error) throw error;

        // Filter unique categories
        const categories = [...new Set(data.map(item => item.category))];

        return res.status(200).json({
            success: true,
            data: categories
        });
    } catch (error) {
        return res.status(500).json({
            success: false,
            message: error.message
        });
    }
};

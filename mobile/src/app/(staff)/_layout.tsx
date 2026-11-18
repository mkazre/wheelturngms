import Feather from '@expo/vector-icons/Feather';
import { Tabs } from 'expo-router';
import { HeaderActions } from '@/components/header';
import { useMe } from '@/lib/auth';
import { usePalette } from '@/lib/theme';

export default function StaffTabs() {
  const p = usePalette();
  const me = useMe();
  const tech = me.mode === 'technician';
  const icon = (name: React.ComponentProps<typeof Feather>['name']) =>
    function TabIcon({ color }: { color: import("react-native").ColorValue }) {
      return <Feather name={name} size={22} color={color as string} />;
    };

  return (
    <Tabs
      screenOptions={{
        headerStyle: { backgroundColor: p.card },
        headerTintColor: p.text,
        headerTitleStyle: { fontWeight: '800', fontSize: 18 },
        headerShadowVisible: false,
        headerRight: () => <HeaderActions />,
        tabBarActiveTintColor: p.dark ? '#ffffff' : p.accent,
        tabBarInactiveTintColor: p.faint,
        tabBarStyle: { backgroundColor: p.tabBar, borderTopColor: p.border },
        tabBarLabelStyle: { fontWeight: '700', fontSize: 11 },
        sceneStyle: { backgroundColor: p.bg },
      }}
    >
      <Tabs.Screen name="home" options={{ title: tech ? 'My work' : 'Dashboard', tabBarLabel: tech ? 'My work' : 'Home', tabBarIcon: icon(tech ? 'tool' : 'home') }} />
      <Tabs.Screen name="jobs" options={{ title: tech ? 'Workshop board' : 'Jobs', tabBarLabel: tech ? 'Board' : 'Jobs', tabBarIcon: icon(tech ? 'columns' : 'clipboard') }} />
      <Tabs.Screen name="diary" options={{ title: 'Diary', tabBarIcon: icon('calendar') }} />
      <Tabs.Screen name="customers" options={{ title: 'Customers', tabBarIcon: icon('users'), href: tech || !me.can.customers ? null : undefined }} />
      <Tabs.Screen name="more" options={{ title: 'More', tabBarIcon: icon('grid') }} />
    </Tabs>
  );
}

export { ErrorScreen as ErrorBoundary } from '@/components/error-screen';

import Feather from '@expo/vector-icons/Feather';
import { Tabs } from 'expo-router';
import { HeaderActions } from '@/components/header';
import { usePalette } from '@/lib/theme';

export default function CustomerTabs() {
  const p = usePalette();
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
      <Tabs.Screen name="garage" options={{ title: 'My garage', tabBarIcon: icon('home') }} />
      <Tabs.Screen name="bills" options={{ title: 'Bills & quotes', tabBarLabel: 'Bills', tabBarIcon: icon('file-text') }} />
      <Tabs.Screen name="book" options={{ title: 'Book a service', tabBarLabel: 'Book', tabBarIcon: icon('calendar') }} />
      <Tabs.Screen name="account" options={{ title: 'Account', tabBarIcon: icon('user') }} />
    </Tabs>
  );
}

export { ErrorScreen as ErrorBoundary } from '@/components/error-screen';
